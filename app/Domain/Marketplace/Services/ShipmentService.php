<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Services;

use App\Domain\Logistics\Giglogistics\Contracts\GiglogisticsGateway;
use App\Domain\Logistics\Giglogistics\Data\CreateShipmentRequest;
use App\Domain\Marketplace\Enums\DigitalOrderStatus;
use App\Domain\Marketplace\Enums\HubVerificationStatus;
use App\Domain\Marketplace\Enums\ShipmentStatus;
use App\Domain\Marketplace\Exceptions\MarketplaceException;
use App\Domain\Marketplace\Models\DigitalOrder;
use App\Domain\Marketplace\Models\MarketplaceShipment;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Models\Hold;
use App\Domain\Transfers\Services\TransferService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Giglogistics hub verification + buyer delivery for physical marketplace orders.
 *
 * Flow: schedule drop-off → hub verifies against locked snapshot → ship to buyer.
 * Partner updates arrive via webhook ({@see GiglogisticsWebhookService}) or polling.
 */
class ShipmentService
{
    public function __construct(
        private readonly GiglogisticsGateway $giglogistics,
        private readonly HubVerificationService $hubVerification,
    ) {}

    /**
     * Seller schedules a hub drop-off — item must be taken to Giglogistics for verification.
     *
     * @param  array<string, string>  $sellerContact
     */
    public function scheduleHubDropoff(DigitalOrder $order, User $seller, array $sellerContact, bool $attestMatchesListing): MarketplaceShipment
    {
        if ((string) $seller->getKey() !== (string) $order->seller_id) {
            throw MarketplaceException::wrongOrderState('seller');
        }

        if (! $order->isPhysical()) {
            throw MarketplaceException::wrongOrderState('physical');
        }

        if (! $attestMatchesListing) {
            throw MarketplaceException::deliveryAttestationRequired();
        }

        if ($order->status !== DigitalOrderStatus::PaidHeld) {
            throw MarketplaceException::alreadyShipped();
        }

        $destination = $order->shipping_address;

        if (! is_array($destination) || empty($destination['line1']) || empty($destination['city'])) {
            throw MarketplaceException::shippingAddressRequired();
        }

        return DB::transaction(function () use ($order, $sellerContact, $destination): MarketplaceShipment {
            $order = DigitalOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== DigitalOrderStatus::PaidHeld || $order->shipment()->exists()) {
                throw MarketplaceException::alreadyShipped();
            }

            $listing = $order->listing;
            $snapshot = (array) ($order->listing_snapshot ?? $listing?->toSnapshot() ?? []);
            $weight = max(100, (int) ($snapshot['weight_grams'] ?? $listing?->weight_grams ?? 500));

            $response = $this->giglogistics->createShipment(new CreateShipmentRequest(
                reference: (string) $order->id,
                weightGrams: $weight,
                origin: $sellerContact,
                destination: $destination,
                description: (string) ($snapshot['title'] ?? $listing?->title ?? 'Physical item'),
                listingSnapshot: $snapshot,
            ));

            $shipment = MarketplaceShipment::create([
                'order_id' => $order->id,
                'carrier' => 'giglogistics',
                'external_id' => $response->externalId,
                'tracking_number' => $response->trackingNumber,
                'dropoff_code' => $response->dropoffCode,
                'hub_name' => $response->hubName,
                'hub_address' => $response->hubAddress,
                'status' => ShipmentStatus::AwaitingDropoff,
                'hub_verification_status' => HubVerificationStatus::Pending,
                'origin_address' => $sellerContact,
                'destination_address' => $destination,
                'events' => [[
                    'status' => ShipmentStatus::AwaitingDropoff->value,
                    'at' => now()->toIso8601String(),
                    'note' => 'Drop-off scheduled. Seller must bring the item to the Giglogistics verification hub.',
                    'event_id' => 'local-scheduled',
                ]],
                'estimated_delivery_at' => now()->addDays($response->estimatedDays + 1),
            ]);

            $order->update([
                'status' => DigitalOrderStatus::AwaitingVerification,
                'seller_attested_at' => now(),
                'shipping_fee' => $response->feeMinor,
            ]);

            $transfer = $order->transfer;
            if ($transfer?->hold instanceof Hold) {
                $transfer->hold->update([
                    'metadata' => [
                        'awaiting_delivery' => true,
                        'awaiting_hub_verification' => true,
                        'order_id' => $order->id,
                        'physical' => true,
                    ],
                ]);
            }

            return $shipment->fresh();
        });
    }

    /** @deprecated Alias for scheduleHubDropoff */
    public function bookShipment(DigitalOrder $order, User $seller, array $pickupAddress, bool $attestMatchesListing): MarketplaceShipment
    {
        return $this->scheduleHubDropoff($order, $seller, $pickupAddress, $attestMatchesListing);
    }

    public function syncShipment(MarketplaceShipment $shipment): bool
    {
        if ($shipment->external_id === null) {
            return false;
        }

        $remote = $this->giglogistics->getStatus($shipment->external_id);

        return $this->applyRemoteUpdate(
            $shipment,
            $remote->status,
            'poll-'.now()->timestamp,
            $remote->events[array_key_last($remote->events)]['note'] ?? $remote->status->label(),
            $remote->verificationReport,
            $remote->hubVerificationScore,
            $remote->hubVerificationStatus,
            $remote->podReference,
            $remote->events,
        );
    }

    /**
     * @param  array<string, mixed>|null  $verificationReport
     * @param  list<array<string, mixed>>|null  $fullEvents
     */
    public function applyRemoteUpdate(
        MarketplaceShipment $shipment,
        ShipmentStatus $status,
        string $eventId,
        string $note,
        ?array $verificationReport = null,
        ?int $hubScore = null,
        ?HubVerificationStatus $hubStatus = null,
        ?string $podReference = null,
        ?array $fullEvents = null,
    ): bool {
        return DB::transaction(function () use ($shipment, $status, $eventId, $note, $verificationReport, $hubScore, $hubStatus, $podReference, $fullEvents): bool {
            $shipment = MarketplaceShipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $order = DigitalOrder::query()->whereKey($shipment->order_id)->lockForUpdate()->firstOrFail();

            $events = (array) ($shipment->events ?? []);

            if (collect($events)->contains(fn (array $e) => ($e['event_id'] ?? '') === $eventId)) {
                return false;
            }

            if ($fullEvents !== null) {
                $events = $fullEvents;
            } else {
                $events[] = [
                    'status' => $status->value,
                    'at' => now()->toIso8601String(),
                    'note' => $note,
                    'event_id' => $eventId,
                ];
            }

            $updates = [
                'status' => $status,
                'events' => $events,
            ];

            if ($status === ShipmentStatus::AtHub) {
                $updates['received_at_hub_at'] = now();
            }

            if (in_array($status, [ShipmentStatus::Verifying, ShipmentStatus::AtHub], true)) {
                $updates['hub_verification_status'] = HubVerificationStatus::Pending;
            }

            if (in_array($status, [ShipmentStatus::VerificationPassed, ShipmentStatus::VerificationFailed], true)) {
                $snapshot = (array) ($order->listing_snapshot ?? []);
                $eval = $verificationReport !== null && $hubScore !== null && $hubStatus !== null
                    ? ['status' => $hubStatus, 'score' => $hubScore, 'report' => $verificationReport]
                    : $this->hubVerification->evaluate($snapshot, $this->findingsFromReport($verificationReport ?? []));

                $updates['hub_verification_status'] = $eval['status'];
                $updates['hub_verification_score'] = $eval['score'];
                $updates['hub_verification_report'] = $eval['report'];
                $updates['verified_at'] = now();
            }

            if ($podReference !== null) {
                $updates['pod_reference'] = $podReference;
            }

            if ($status === ShipmentStatus::Delivered) {
                $updates['delivered_at'] = now();
            }

            $shipment->update($updates);
            $changed = false;

            if ($status === ShipmentStatus::VerificationPassed && $order->status === DigitalOrderStatus::AwaitingVerification) {
                $order->update([
                    'status' => DigitalOrderStatus::Shipped,
                    'shipped_at' => now(),
                ]);
                $changed = true;
            }

            if ($status === ShipmentStatus::VerificationFailed) {
                $this->refundOrder($order, 'hub_verification_failed');
                $changed = true;
            }

            if ($status === ShipmentStatus::Delivered && $order->status === DigitalOrderStatus::Shipped) {
                $order->update([
                    'status' => DigitalOrderStatus::Delivered,
                    'delivered_at' => now(),
                    'received_at' => now(),
                ]);

                $transfer = $order->transfer;
                if ($transfer?->hold instanceof Hold) {
                    $confirmHours = (int) config('reton.physical.confirm_hours', 72);
                    $transfer->hold->update([
                        'metadata' => ['awaiting_delivery' => false, 'order_id' => $order->id, 'physical' => true],
                        'expires_at' => now()->addHours($confirmHours),
                    ]);
                }
                $changed = true;
            }

            if ($status === ShipmentStatus::Failed && in_array($order->status, [DigitalOrderStatus::AwaitingVerification, DigitalOrderStatus::Shipped], true)) {
                $this->refundOrder($order, 'giglogistics_delivery_failed');
                $changed = true;
            }

            return $changed;
        });
    }

    /** @param  array<string, mixed>  $report */
    private function findingsFromReport(array $report): array
    {
        $checks = (array) ($report['checks'] ?? []);

        return [
            'weight_grams' => $checks['weight']['found'] ?? null,
            'condition' => $checks['condition']['found'] ?? null,
            'brand' => $checks['brand']['found'] ?? null,
            'detail' => $checks['detail']['found'] ?? null,
            'notes' => $report['inspector_notes'] ?? '',
        ];
    }

    private function refundOrder(DigitalOrder $order, string $reason): void
    {
        $transfer = $order->transfer;

        if ($transfer !== null && $transfer->status === TransferStatus::Held) {
            app(TransferService::class)->refund($transfer, $reason);
        }

        $order->update(['status' => DigitalOrderStatus::Refunded]);
    }
}
