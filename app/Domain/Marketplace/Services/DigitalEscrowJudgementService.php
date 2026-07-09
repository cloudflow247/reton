<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Services;

use App\Domain\Callback\Enums\CallbackResolution;
use App\Domain\Callback\Models\Callback;
use App\Domain\Fraud\Contracts\FraudScorer;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Enums\FraudRiskLevel;
use App\Domain\Marketplace\Enums\DigitalDisputeCategory;
use App\Domain\Marketplace\Enums\DigitalOrderStatus;
use App\Domain\Marketplace\Enums\ItemType;
use App\Domain\Marketplace\Exceptions\MarketplaceException;
use App\Domain\Marketplace\Models\DigitalOrder;
use App\Domain\Transfers\Models\Transfer;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Carbon;

/**
 * Fair escrow judgement for peer-to-peer digital item trades.
 *
 * Combines order state, dispute category, delivery deadlines, seller trust history,
 * and fraud signals to decide when disputes are allowed and how unanswered callbacks resolve.
 */
class DigitalEscrowJudgementService
{
    public function __construct(
        private readonly FraudScorer $fraud,
        private readonly ListingVerificationService $verification,
    ) {}

    public function sellerTrustScore(User $seller): int
    {
        $completed = DigitalOrder::query()
            ->where('seller_id', $seller->getKey())
            ->where('status', DigitalOrderStatus::Completed)
            ->count();

        $refunded = DigitalOrder::query()
            ->where('seller_id', $seller->getKey())
            ->where('status', DigitalOrderStatus::Refunded)
            ->count();

        $disputed = DigitalOrder::query()
            ->where('seller_id', $seller->getKey())
            ->where('status', DigitalOrderStatus::Disputed)
            ->count();

        $total = $completed + $refunded + $disputed;

        if ($total === 0) {
            return 70;
        }

        $successRate = $completed / max(1, $total);
        $score = (int) round(50 + ($successRate * 50));
        $score -= min(30, $refunded * 8);
        $score -= min(20, $disputed * 4);

        $score += $this->sellerDeliveryReliabilityAdjustment($seller);

        $missedDeadlines = DigitalOrder::query()
            ->where('seller_id', $seller->getKey())
            ->where('status', DigitalOrderStatus::Refunded)
            ->where('dispute_category', DigitalDisputeCategory::NotDelivered->value)
            ->count();

        $score -= min(15, $missedDeadlines * 5);

        return max(0, min(100, $score));
    }

    public function buyerTrustScore(User $buyer): int
    {
        $completed = DigitalOrder::query()
            ->where('buyer_id', $buyer->getKey())
            ->where('status', DigitalOrderStatus::Completed)
            ->count();

        $refunded = DigitalOrder::query()
            ->where('buyer_id', $buyer->getKey())
            ->where('status', DigitalOrderStatus::Refunded)
            ->count();

        $disputed = DigitalOrder::query()
            ->where('buyer_id', $buyer->getKey())
            ->where('status', DigitalOrderStatus::Disputed)
            ->count();

        $total = $completed + $refunded + $disputed;

        if ($total === 0) {
            return 75;
        }

        $fairRate = $completed / max(1, $total);
        $score = (int) round(55 + ($fairRate * 45));
        $score -= min(35, $refunded * 6);
        $score -= min(25, $disputed * 5);

        return max(0, min(100, $score));
    }

    public function assertCanRaiseDispute(DigitalOrder $order, User $buyer, DigitalDisputeCategory $category): void
    {
        if ((string) $buyer->getKey() !== (string) $order->buyer_id) {
            throw MarketplaceException::wrongOrderState('buyer');
        }

        if (! in_array($order->status, [
            DigitalOrderStatus::PaidHeld,
            DigitalOrderStatus::AwaitingVerification,
            DigitalOrderStatus::Shipped,
            DigitalOrderStatus::Delivered,
        ], true)) {
            throw MarketplaceException::disputeNotAllowed();
        }

        $allowed = DigitalDisputeCategory::forOrderStatus($order->status, $this->itemType($order));

        if (! in_array($category, $allowed, true)) {
            throw MarketplaceException::disputeCategoryMismatch($category->label());
        }

        if ($category === DigitalDisputeCategory::NotDelivered) {
            $graceHours = (int) config('reton.digital.dispute_grace_hours', 24);
            $graceEnds = $order->created_at?->copy()->addHours($graceHours);

            if ($graceEnds instanceof Carbon && now()->lt($graceEnds)) {
                throw MarketplaceException::disputeTooEarly($graceHours);
            }
        }

        if (in_array($category, [DigitalDisputeCategory::NotAsDescribed, DigitalDisputeCategory::InvalidItem, DigitalDisputeCategory::DamagedInTransit, DigitalDisputeCategory::WrongItem], true)
            && $order->status !== DigitalOrderStatus::Delivered) {
            throw MarketplaceException::notDeliveredYet();
        }
    }

    public function assertCanInitiateGenericCallback(DigitalOrder $order, User $buyer, string $reason): DigitalDisputeCategory
    {
        if ($order->status === DigitalOrderStatus::Delivered) {
            throw MarketplaceException::useStructuredDispute();
        }

        // PaidHeld, Shipped, and AwaitingVerification all allow NotDelivered
        // via assertCanRaiseDispute + DigitalDisputeCategory::forOrderStatus.
        $this->assertCanRaiseDispute($order, $buyer, DigitalDisputeCategory::NotDelivered);

        return DigitalDisputeCategory::NotDelivered;
    }

    /**
     * Decide who wins when a digital-item callback expires unanswered.
     */
    public function resolveOnCallbackExpiry(Callback $callback, DigitalOrder $order): CallbackResolution
    {
        $transfer = $callback->transfer;
        $category = DigitalDisputeCategory::tryFrom((string) ($order->dispute_category ?? ''));
        $seller = User::find($order->seller_id);
        $buyer = User::find($order->buyer_id);

        $sellerHighRisk = $seller instanceof User && $this->userIsHighRisk($seller, $transfer, 'callback_expiry');
        $buyerHighRisk = $buyer instanceof User && $this->userIsHighRisk($buyer, $transfer, 'callback_expiry');
        $sellerTrust = $seller instanceof User ? $this->sellerTrustScore($seller) : 70;
        $buyerTrust = $buyer instanceof User ? $this->buyerTrustScore($buyer) : 75;
        $descriptionMatch = $this->verification->descriptionMatchScore($order);
        $hubScore = (int) ($order->shipment?->hub_verification_score ?? 0);
        $hubPassed = $order->shipment?->isHubVerified() ?? false;
        $isPhysical = $this->itemType($order) === ItemType::Physical;

        if ($sellerHighRisk) {
            return CallbackResolution::Refund;
        }

        if ($category === DigitalDisputeCategory::NotDelivered) {
            if (! $order->isDelivered()) {
                return CallbackResolution::Refund;
            }

            if ($buyerHighRisk && $sellerTrust >= 60) {
                return CallbackResolution::Release;
            }

            return CallbackResolution::Refund;
        }

        if ($category === DigitalDisputeCategory::DamagedInTransit && $isPhysical) {
            if ($sellerTrust >= 75 && $descriptionMatch >= 80) {
                return CallbackResolution::Release;
            }

            return CallbackResolution::Refund;
        }

        if ($category === DigitalDisputeCategory::WrongItem && $isPhysical) {
            return CallbackResolution::Refund;
        }

        if (in_array($category, [DigitalDisputeCategory::NotAsDescribed, DigitalDisputeCategory::DamagedInTransit, DigitalDisputeCategory::WrongItem], true)) {
            if ($isPhysical && $hubPassed && $hubScore >= 85) {
                if ($category === DigitalDisputeCategory::WrongItem) {
                    return CallbackResolution::Refund;
                }

                if ($buyerHighRisk && $sellerTrust >= 70) {
                    return CallbackResolution::Release;
                }

                if ($category === DigitalDisputeCategory::DamagedInTransit) {
                    return CallbackResolution::Refund;
                }

                if ($descriptionMatch >= 70 && $sellerTrust >= 60) {
                    return CallbackResolution::Release;
                }
            }
        }

        if (in_array($category, [DigitalDisputeCategory::NotAsDescribed, DigitalDisputeCategory::InvalidItem], true)) {
            if ($category === DigitalDisputeCategory::InvalidItem) {
                if ($sellerTrust >= 80 && $buyerHighRisk && ! $sellerHighRisk) {
                    return CallbackResolution::Release;
                }

                return CallbackResolution::Refund;
            }

            if ($descriptionMatch >= 85 && $buyerHighRisk && $sellerTrust >= 65) {
                return CallbackResolution::Release;
            }

            if ($buyerHighRisk && $sellerTrust >= 70 && ! $sellerHighRisk) {
                return CallbackResolution::Release;
            }

            if ($sellerTrust >= 60 && $buyerTrust < 45) {
                return CallbackResolution::Release;
            }

            if ($sellerTrust < 40 || $descriptionMatch < 50) {
                return CallbackResolution::Refund;
            }

            return CallbackResolution::Refund;
        }

        if ($buyerHighRisk && $sellerTrust >= 70) {
            return CallbackResolution::Release;
        }

        if ($buyerTrust >= 80 && $sellerTrust < 50) {
            return CallbackResolution::Refund;
        }

        return CallbackResolution::Refund;
    }

    /**
     * @return array<string, mixed>
     */
    public function guidanceFor(DigitalOrder $order, User $viewer): array
    {
        $role = $this->roleFor($order, $viewer);
        $sellerTrust = $this->sellerTrustScore(User::find($order->seller_id) ?? $viewer);
        $confirmDeadline = $order->transfer?->hold?->expires_at;
        $isPhysical = $this->itemType($order) === ItemType::Physical;
        $graceHours = $isPhysical
            ? (int) config('reton.physical.dispute_grace_hours', 48)
            : (int) config('reton.digital.dispute_grace_hours', 24);
        $graceEnds = $order->created_at?->copy()->addHours($graceHours);
        $allowedDisputes = array_map(
            fn (DigitalDisputeCategory $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'hint' => $c->hint(),
            ],
            DigitalDisputeCategory::forOrderStatus($order->status, $this->itemType($order)),
        );

        $step = match ($order->status) {
            DigitalOrderStatus::PaidHeld => 1,
            DigitalOrderStatus::AwaitingVerification => 2,
            DigitalOrderStatus::Shipped => 3,
            DigitalOrderStatus::Delivered => $isPhysical ? 4 : 2,
            DigitalOrderStatus::Completed => $isPhysical ? 5 : 4,
            DigitalOrderStatus::Refunded => $isPhysical ? 5 : 4,
            DigitalOrderStatus::Disputed => $isPhysical ? 4 : 3,
        };

        $stepLabel = match ($order->status) {
            DigitalOrderStatus::PaidHeld => $isPhysical ? 'Schedule hub drop-off' : 'Waiting for delivery',
            DigitalOrderStatus::AwaitingVerification => 'Giglogistics verifying item',
            DigitalOrderStatus::Shipped => 'In transit to buyer',
            DigitalOrderStatus::Delivered => 'Review your item',
            DigitalOrderStatus::Disputed => 'Dispute in progress',
            DigitalOrderStatus::Completed => 'Complete',
            DigitalOrderStatus::Refunded => 'Refunded',
        };

        $nextAction = match (true) {
            $role === 'seller' && $order->status === DigitalOrderStatus::PaidHeld && $isPhysical => 'seller_schedule_dropoff',
            $role === 'seller' && $order->status === DigitalOrderStatus::AwaitingVerification && $isPhysical => 'seller_await_hub',
            $role === 'seller' && $order->status === DigitalOrderStatus::PaidHeld && ! $isPhysical => 'seller_deliver',
            $role === 'buyer' && $order->status === DigitalOrderStatus::Delivered => 'buyer_confirm',
            $role === 'buyer' && in_array($order->status, [DigitalOrderStatus::PaidHeld, DigitalOrderStatus::AwaitingVerification, DigitalOrderStatus::Shipped], true) && $this->canDisputeNotDelivered($order) => 'buyer_dispute_not_delivered',
            $order->status === DigitalOrderStatus::Disputed => 'await_resolution',
            default => null,
        };

        $snapshot = $order->listing_snapshot ?? [];

        return [
            'step' => $step,
            'step_label' => $stepLabel,
            'next_action' => $nextAction,
            'allowed_disputes' => $allowedDisputes,
            'delivery_deadline_at' => $order->delivery_deadline_at,
            'confirm_deadline_at' => $confirmDeadline,
            'dispute_grace_ends_at' => $graceEnds,
            'can_dispute_not_delivered' => $role === 'buyer' && $this->canDisputeNotDelivered($order),
            'can_dispute_quality' => $role === 'buyer' && $order->status === DigitalOrderStatus::Delivered,
            'auto_refund_at' => in_array($order->status, [DigitalOrderStatus::PaidHeld, DigitalOrderStatus::AwaitingVerification, DigitalOrderStatus::Shipped], true) ? $order->delivery_deadline_at : null,
            'seller_trust_score' => $sellerTrust,
            'verification_score' => $order->verification_score,
            'item_type' => $isPhysical ? ItemType::Physical->value : ItemType::Digital->value,
            'listing_description' => $role === 'buyer'
                ? ($snapshot['description'] ?? $order->listing?->description)
                : null,
            'listing_snapshot' => $role === 'buyer' ? $snapshot : null,
            'shipment' => $order->shipment ? [
                'tracking_number' => $order->shipment->tracking_number,
                'dropoff_code' => $order->shipment->dropoff_code,
                'status' => $order->shipment->status->value,
                'status_label' => $order->shipment->status->label(),
                'carrier' => 'Giglogistics',
                'hub_name' => $order->shipment->hub_name,
                'hub_address' => $order->shipment->hub_address,
                'hub_verification_status' => $order->shipment->hub_verification_status?->value,
                'hub_verification_score' => $order->shipment->hub_verification_score,
                'hub_verification_report' => $order->shipment->hub_verification_report,
                'events' => $order->shipment->events ?? [],
                'estimated_delivery_at' => $order->shipment->estimated_delivery_at,
            ] : null,
        ];
    }

    public function canDisputeNotDelivered(DigitalOrder $order): bool
    {
        if (! in_array($order->status, [DigitalOrderStatus::PaidHeld, DigitalOrderStatus::AwaitingVerification, DigitalOrderStatus::Shipped], true)) {
            return false;
        }

        $isPhysical = $this->itemType($order) === ItemType::Physical;
        $graceHours = $isPhysical
            ? (int) config('reton.physical.dispute_grace_hours', 48)
            : (int) config('reton.digital.dispute_grace_hours', 24);
        $graceEnds = $order->created_at?->copy()->addHours($graceHours);

        return $graceEnds instanceof Carbon && now()->gte($graceEnds);
    }

    public function payloadChecksum(string $payload): string
    {
        return hash('sha256', $payload);
    }

    private function sellerDeliveryReliabilityAdjustment(User $seller): int
    {
        $delivered = DigitalOrder::query()
            ->where('seller_id', $seller->getKey())
            ->whereNotNull('delivered_at')
            ->whereNotNull('delivery_deadline_at')
            ->get(['delivered_at', 'delivery_deadline_at']);

        if ($delivered->isEmpty()) {
            return 0;
        }

        $onTime = $delivered->filter(
            fn (DigitalOrder $order) => $order->delivered_at?->lte($order->delivery_deadline_at),
        )->count();

        $onTimeRate = $onTime / $delivered->count();

        return (int) round(($onTimeRate - 0.5) * 20);
    }

    private function roleFor(DigitalOrder $order, User $viewer): ?string
    {
        if ((string) $viewer->getKey() === (string) $order->buyer_id) {
            return 'buyer';
        }

        if ((string) $viewer->getKey() === (string) $order->seller_id) {
            return 'seller';
        }

        return null;
    }

    private function userIsHighRisk(User $user, ?Transfer $transfer, string $action): bool
    {
        if ($transfer === null) {
            return false;
        }

        $wallet = $user->wallets()->where('currency', $transfer->currency)->first();

        if ($wallet === null) {
            return false;
        }

        $assessment = $this->fraud->score(new FraudContext(
            user: $user,
            wallet: $wallet,
            amount: Money::of($transfer->amount, $transfer->currency),
            action: $action,
        ));

        return $assessment->level === FraudRiskLevel::High;
    }

    private function itemType(DigitalOrder $order): ItemType
    {
        $value = $order->listing_snapshot['item_type'] ?? $order->listing?->item_type?->value ?? ItemType::Digital->value;

        return ItemType::tryFrom((string) $value) ?? ItemType::Digital;
    }
}
