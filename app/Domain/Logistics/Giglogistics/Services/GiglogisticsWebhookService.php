<?php

declare(strict_types=1);

namespace App\Domain\Logistics\Giglogistics\Services;

use App\Domain\Marketplace\Enums\HubVerificationStatus;
use App\Domain\Marketplace\Enums\ShipmentStatus;
use App\Domain\Marketplace\Models\MarketplaceShipment;
use App\Domain\Marketplace\Services\ShipmentService;
use Illuminate\Support\Facades\Log;

/**
 * Processes signed webhook callbacks from Giglogistics partner hubs.
 *
 * Events: shipment.at_hub, shipment.verifying, shipment.verification_passed,
 * shipment.verification_failed, shipment.in_transit, shipment.delivered
 */
class GiglogisticsWebhookService
{
    public function __construct(private readonly ShipmentService $shipments) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $event, array $payload): void
    {
        $externalId = (string) ($payload['shipment_id'] ?? $payload['external_id'] ?? '');

        if ($externalId === '') {
            return;
        }

        $shipment = MarketplaceShipment::query()->where('external_id', $externalId)->first();

        if (! $shipment instanceof MarketplaceShipment) {
            Log::warning('Giglogistics webhook for unknown shipment', ['external_id' => $externalId, 'event' => $event]);

            return;
        }

        $eventId = (string) ($payload['event_id'] ?? $event.'-'.($payload['occurred_at'] ?? now()->timestamp));

        $events = (array) ($shipment->events ?? []);

        if (collect($events)->contains(fn (array $e) => ($e['event_id'] ?? '') === $eventId)) {
            return;
        }

        $status = $this->mapEventToStatus($event, $payload);

        if ($status === null) {
            return;
        }

        $this->shipments->applyRemoteUpdate(
            $shipment,
            $status,
            $eventId,
            (string) ($payload['note'] ?? $this->defaultNote($event)),
            isset($payload['verification_report']) && is_array($payload['verification_report'])
                ? $payload['verification_report']
                : null,
            isset($payload['hub_verification_score']) ? (int) $payload['hub_verification_score'] : null,
            isset($payload['hub_verification_status'])
                ? HubVerificationStatus::tryFrom((string) $payload['hub_verification_status'])
                : null,
            isset($payload['pod_reference']) ? (string) $payload['pod_reference'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapEventToStatus(string $event, array $payload): ?ShipmentStatus
    {
        if (isset($payload['status']) && is_string($payload['status'])) {
            return ShipmentStatus::tryFrom($payload['status']);
        }

        return match ($event) {
            'shipment.awaiting_dropoff' => ShipmentStatus::AwaitingDropoff,
            'shipment.at_hub' => ShipmentStatus::AtHub,
            'shipment.verifying' => ShipmentStatus::Verifying,
            'shipment.verification_passed' => ShipmentStatus::VerificationPassed,
            'shipment.verification_failed' => ShipmentStatus::VerificationFailed,
            'shipment.in_transit' => ShipmentStatus::InTransit,
            'shipment.out_for_delivery' => ShipmentStatus::OutForDelivery,
            'shipment.delivered' => ShipmentStatus::Delivered,
            'shipment.failed' => ShipmentStatus::Failed,
            default => null,
        };
    }

    private function defaultNote(string $event): string
    {
        return match ($event) {
            'shipment.at_hub' => 'Package received at Giglogistics verification hub.',
            'shipment.verifying' => 'Hub staff inspecting item against Reton listing snapshot.',
            'shipment.verification_passed' => 'Item matches the locked order description — shipping to buyer.',
            'shipment.verification_failed' => 'Item did not match the listing — buyer will be refunded.',
            'shipment.in_transit' => 'Verified package en route to buyer.',
            'shipment.delivered' => 'Delivered — proof of delivery captured.',
            default => 'Giglogistics status update.',
        };
    }
}
