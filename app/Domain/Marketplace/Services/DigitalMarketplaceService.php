<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Services;

use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Callback\Models\Callback;
use App\Domain\Callback\Services\CallbackService;
use App\Domain\Marketplace\Enums\DigitalDisputeCategory;
use App\Domain\Marketplace\Enums\DigitalOrderStatus;
use App\Domain\Marketplace\Enums\ItemCondition;
use App\Domain\Marketplace\Enums\ItemType;
use App\Domain\Marketplace\Enums\ListingStatus;
use App\Domain\Marketplace\Enums\VerificationStatus;
use App\Domain\Marketplace\Exceptions\MarketplaceException;
use App\Domain\Marketplace\Models\DigitalListing;
use App\Domain\Marketplace\Models\DigitalOrder;
use App\Domain\Marketplace\Support\ListingItemCodes;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Models\Hold;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Digital goods marketplace on top of protected transfers.
 *
 * Buyer = transfer sender (pays, releases, callbacks).
 * Seller = transfer receiver (delivers item, responds to disputes).
 */
class DigitalMarketplaceService
{
    public function __construct(
        private readonly TransferService $transfers,
        private readonly DigitalEscrowJudgementService $escrow,
        private readonly ListingVerificationService $verification,
    ) {}

    public function createListing(
        User $seller,
        string $title,
        string $description,
        Money $price,
        string $deliveryPayload,
    ): DigitalListing {
        $listing = DigitalListing::create([
            'item_code' => ListingItemCodes::generate(),
            'seller_id' => $seller->getKey(),
            'item_type' => ItemType::Digital,
            'title' => $title,
            'description' => $description,
            'price' => $price->amount,
            'currency' => $price->currency,
            'delivery_payload' => $deliveryPayload,
            'status' => ListingStatus::Active,
        ]);

        $verified = $this->verification->verifyListing($listing);
        $listing->update([
            'verification_status' => $verified['status'],
            'verification_score' => $verified['score'],
        ]);

        return $listing->refresh();
    }

    /**
     * @param  array<string, string>  $specs
     */
    public function createPhysicalListing(
        User $seller,
        string $title,
        string $description,
        Money $price,
        ItemCondition $condition,
        int $weightGrams,
        array $specs,
        ?string $handlingNotes = null,
        ?array $dimensionsCm = null,
    ): DigitalListing {
        $listing = DigitalListing::create([
            'item_code' => ListingItemCodes::generate(),
            'seller_id' => $seller->getKey(),
            'item_type' => ItemType::Physical,
            'title' => $title,
            'description' => $description,
            'condition' => $condition,
            'weight_grams' => $weightGrams,
            'dimensions_cm' => $dimensionsCm,
            'specs' => $specs,
            'handling_notes' => $handlingNotes,
            'price' => $price->amount,
            'currency' => $price->currency,
            'delivery_payload' => '',
            'status' => ListingStatus::Active,
        ]);

        $verified = $this->verification->verifyListing($listing);

        if ($verified['status'] === VerificationStatus::Flagged) {
            $listing->delete();
            throw MarketplaceException::listingVerificationFailed();
        }

        $listing->update([
            'verification_status' => $verified['status'],
            'verification_score' => $verified['score'],
        ]);

        return $listing->refresh();
    }

    public function purchase(
        User $buyer,
        DigitalListing $listing,
        Wallet $buyerWallet,
        bool $buyerAcceptsDescription = false,
        ?array $shippingAddress = null,
    ): DigitalOrder {
        if ((string) $buyer->getKey() === (string) $listing->seller_id) {
            throw MarketplaceException::cannotBuyOwnListing();
        }

        if (! $listing->isActive()) {
            throw MarketplaceException::listingUnavailable();
        }

        $sellerWallet = Wallet::query()
            ->where('owner_type', User::class)
            ->where('owner_id', $listing->seller_id)
            ->where('currency', $listing->currency)
            ->firstOrFail();

        if ($buyerWallet->currency !== $listing->currency) {
            throw MarketplaceException::listingUnavailable();
        }

        if ($listing->isPhysical()) {
            if (! $buyerAcceptsDescription) {
                throw MarketplaceException::buyerMustAcceptDescription();
            }

            if (! is_array($shippingAddress) || empty($shippingAddress['line1']) || empty($shippingAddress['city'])) {
                throw MarketplaceException::shippingAddressRequired();
            }
        }

        $amount = Money::of($listing->price, $listing->currency);
        $idempotencyKey = 'digital-purchase-'.$listing->id.'-'.$buyer->getKey();
        $isPhysical = $listing->isPhysical();
        $deadlineHours = $isPhysical
            ? (int) config('reton.physical.ship_deadline_hours', 48)
            : (int) config('reton.digital.delivery_deadline_hours', 72);

        return DB::transaction(function () use ($buyer, $listing, $buyerWallet, $sellerWallet, $amount, $idempotencyKey, $deadlineHours, $buyerAcceptsDescription, $shippingAddress, $isPhysical): DigitalOrder {
            $listing = DigitalListing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();

            if (! $listing->isActive()) {
                throw MarketplaceException::listingUnavailable();
            }

            $snapshot = $listing->toSnapshot();

            $order = DigitalOrder::create([
                'listing_id' => $listing->id,
                'buyer_id' => $buyer->getKey(),
                'seller_id' => $listing->seller_id,
                'status' => DigitalOrderStatus::PaidHeld,
                'listing_snapshot' => $snapshot,
                'buyer_accepted_description_at' => $buyerAcceptsDescription ? now() : null,
                'shipping_address' => $shippingAddress,
                'delivery_deadline_at' => now()->addHours($deadlineHours),
            ]);

            $orderVerification = $this->verification->verifyOrderSnapshot($order);
            $order->update([
                'verification_status' => $orderVerification['status'],
                'verification_score' => $orderVerification['score'],
            ]);

            $purpose = $isPhysical ? 'physical_item' : 'digital_item';

            $transfer = $this->transfers->sendProtected(
                $buyer,
                $buyerWallet,
                $sellerWallet,
                $amount,
                ($isPhysical ? 'Physical' : 'Digital').' purchase: '.$listing->title,
                $idempotencyKey,
            );

            $transfer->update([
                'metadata' => [
                    'purpose' => $purpose,
                    'order_id' => $order->id,
                    'listing_id' => $listing->id,
                    'listing_title' => $listing->title,
                    'listing_snapshot_checksum' => $snapshot['checksum'] ?? null,
                ],
            ]);

            $hold = $transfer->hold;
            if ($hold instanceof Hold) {
                $hold->update([
                    'expires_at' => null,
                    'metadata' => ['awaiting_delivery' => true, 'order_id' => $order->id],
                ]);
            }

            $order->update(['transfer_id' => $transfer->id]);
            $listing->update(['status' => ListingStatus::Sold]);

            return $order->load(['listing', 'transfer.hold']);
        });
    }

    public function deliver(DigitalOrder $order, User $seller, bool $attestMatchesListing): DigitalOrder
    {
        if ($order->isPhysical()) {
            throw MarketplaceException::wrongOrderState('physical_use_ship');
        }

        if ((string) $seller->getKey() !== (string) $order->seller_id) {
            throw MarketplaceException::wrongOrderState('seller');
        }

        if (! $attestMatchesListing) {
            throw MarketplaceException::deliveryAttestationRequired();
        }

        if ($order->status !== DigitalOrderStatus::PaidHeld) {
            throw MarketplaceException::alreadyDelivered();
        }

        return DB::transaction(function () use ($order): DigitalOrder {
            $order = DigitalOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== DigitalOrderStatus::PaidHeld) {
                throw MarketplaceException::alreadyDelivered();
            }

            $listing = $order->listing;
            $payload = (string) ($listing?->delivery_payload ?? '');

            $order->update([
                'status' => DigitalOrderStatus::Delivered,
                'delivered_at' => now(),
                'seller_attested_at' => now(),
                'payload_checksum' => $this->escrow->payloadChecksum($payload),
            ]);

            $transfer = $order->transfer;
            if ($transfer?->hold instanceof Hold) {
                $transfer->hold->update([
                    'metadata' => ['awaiting_delivery' => false, 'order_id' => $order->id],
                    'expires_at' => now()->addHours((int) config('reton.digital.confirm_hours', 48)),
                ]);
            }

            return $order->refresh()->load(['listing', 'transfer.hold']);
        });
    }

    /**
     * Buyer confirms the item matches the listing and releases escrow in one step.
     */
    public function confirmSatisfaction(DigitalOrder $order, User $buyer): DigitalOrder
    {
        if ((string) $buyer->getKey() !== (string) $order->buyer_id) {
            throw MarketplaceException::wrongOrderState('buyer');
        }

        if ($order->status !== DigitalOrderStatus::Delivered) {
            throw MarketplaceException::notDeliveredYet();
        }

        return DB::transaction(function () use ($order, $buyer): DigitalOrder {
            $order = DigitalOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $transfer = $order->transfer;

            if ($transfer === null) {
                throw MarketplaceException::wrongOrderState('transfer');
            }

            $this->assertBuyerCanRelease($transfer);

            $order->update([
                'buyer_reviewed_at' => now(),
                'buyer_satisfied' => true,
            ]);

            $this->transfers->release($transfer);

            return $order->refresh()->load(['listing', 'transfer.hold']);
        });
    }

    public function raiseDispute(
        DigitalOrder $order,
        User $buyer,
        DigitalDisputeCategory $category,
        string $details,
    ): Callback {
        $this->escrow->assertCanRaiseDispute($order, $buyer, $category);

        return DB::transaction(function () use ($order, $buyer, $category, $details): Callback {
            $order = DigitalOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $transfer = $order->transfer;

            if ($transfer === null) {
                throw MarketplaceException::wrongOrderState('transfer');
            }

            $order->update([
                'dispute_category' => $category->value,
                'buyer_reviewed_at' => now(),
                'buyer_satisfied' => false,
            ]);

            $reason = sprintf('[%s] %s', $category->label(), trim($details));

            return app(CallbackService::class)->initiate($transfer, $buyer, $reason);
        });
    }

    public function assertCanInitiateGenericCallback(DigitalOrder $order, User $buyer, string $reason): void
    {
        $category = $this->escrow->assertCanInitiateGenericCallback($order, $buyer, $reason);

        $order->update([
            'dispute_category' => $category->value,
            'buyer_satisfied' => false,
        ]);
    }

    public function assertBuyerCanRelease(Transfer $transfer): void
    {
        $order = DigitalOrder::query()->where('transfer_id', $transfer->id)->first();

        if (! $order instanceof DigitalOrder) {
            return;
        }

        if ($order->status !== DigitalOrderStatus::Delivered) {
            throw MarketplaceException::notDeliveredYet();
        }

        if ($order->isPhysical() && ! $order->shipment?->isDelivered()) {
            throw MarketplaceException::notShippedYet();
        }
    }

    public function blocksAutoRelease(Hold $hold, Transfer $transfer): bool
    {
        if (($hold->metadata['awaiting_delivery'] ?? false) === true) {
            return true;
        }

        $purpose = $transfer->metadata['purpose'] ?? null;

        if (in_array($purpose, ['digital_item', 'physical_item'], true) && $hold->expires_at === null) {
            return true;
        }

        return false;
    }

    public function syncOrderFromTransfer(Transfer $transfer): void
    {
        $order = DigitalOrder::query()->where('transfer_id', $transfer->id)->first();

        if (! $order instanceof DigitalOrder) {
            return;
        }

        match ($transfer->status) {
            TransferStatus::Completed => $order->update([
                'status' => DigitalOrderStatus::Completed,
                'completed_at' => now(),
            ]),
            TransferStatus::Refunded => $order->update([
                'status' => DigitalOrderStatus::Refunded,
            ]),
            default => null,
        };
    }

    public function markDisputed(Transfer $transfer): void
    {
        DigitalOrder::query()
            ->where('transfer_id', $transfer->id)
            ->whereIn('status', [DigitalOrderStatus::PaidHeld->value, DigitalOrderStatus::AwaitingVerification->value, DigitalOrderStatus::Shipped->value, DigitalOrderStatus::Delivered->value])
            ->update(['status' => DigitalOrderStatus::Disputed->value]);
    }

    /**
     * Auto-refund when the seller misses the delivery deadline (scheduler).
     * Skips orders with an open callback — the dispute flow handles those.
     */
    public function refundOverdueUndelivered(DigitalOrder $order): bool
    {
        return DB::transaction(function () use ($order): bool {
            $order = DigitalOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== DigitalOrderStatus::PaidHeld) {
                return false;
            }

            // Physical orders with a booked shipment are in transit — do not
            // auto-refund as "undelivered"; hub/dispute flows own those cases.
            if ($order->isPhysical() && $order->shipment()->exists()) {
                return false;
            }

            if ($order->delivery_deadline_at === null || $order->delivery_deadline_at->isFuture()) {
                return false;
            }

            $transfer = $order->transfer;

            if ($transfer === null || $transfer->status !== TransferStatus::Held) {
                return false;
            }

            if ($this->hasOpenCallback($transfer)) {
                return false;
            }

            $order->update([
                'dispute_category' => DigitalDisputeCategory::NotDelivered->value,
                'buyer_satisfied' => false,
            ]);

            $this->transfers->refund($transfer, 'delivery_deadline_exceeded');

            return true;
        });
    }

    private function hasOpenCallback(Transfer $transfer): bool
    {
        return Callback::query()
            ->where('transfer_id', $transfer->id)
            ->whereIn('status', [CallbackStatus::Pending->value, CallbackStatus::Escalated->value])
            ->exists();
    }

    /** @return array<string, mixed> */
    public function deliveryPayloadForBuyer(DigitalOrder $order, User $viewer): ?array
    {
        if ((string) $viewer->getKey() !== (string) $order->buyer_id) {
            return null;
        }

        if ($order->isPhysical()) {
            if (! $order->isDelivered()) {
                return null;
            }

            return [
                'title' => $order->listing_snapshot['title'] ?? $order->listing?->title,
                'description' => $order->listing_snapshot['description'] ?? $order->listing?->description,
                'specs' => $order->listing_snapshot['specs'] ?? [],
                'condition' => $order->listing_snapshot['condition'] ?? null,
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'integrity_verified' => $this->verification->descriptionMatchScore($order) >= 80,
                'shipment' => $order->shipment ? [
                    'tracking_number' => $order->shipment->tracking_number,
                    'carrier' => 'Giglogistics',
                    'pod_reference' => $order->shipment->pod_reference,
                ] : null,
            ];
        }

        if (! $order->isDelivered()) {
            return null;
        }

        $listing = $order->listing;
        $content = (string) ($listing?->delivery_payload ?? '');
        $checksumMatches = $order->payload_checksum === null
            || $order->payload_checksum === $this->escrow->payloadChecksum($content);

        return [
            'title' => $listing?->title,
            'content' => $content,
            'description' => $listing?->description,
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'integrity_verified' => $checksumMatches,
        ];
    }
}
