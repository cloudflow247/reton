<?php

declare(strict_types=1);

namespace App\Domain\Logistics\Giglogistics\Gateways;

use App\Domain\Logistics\Giglogistics\Contracts\GiglogisticsGateway;
use App\Domain\Logistics\Giglogistics\Data\CreateShipmentRequest;
use App\Domain\Logistics\Giglogistics\Data\ShipmentQuote;
use App\Domain\Logistics\Giglogistics\Data\ShipmentResponse;
use App\Domain\Logistics\Giglogistics\Data\ShipmentStatusResponse;
use App\Domain\Marketplace\Enums\HubVerificationStatus;
use App\Domain\Marketplace\Enums\ShipmentStatus;
use App\Domain\Marketplace\Services\HubVerificationService;
use Illuminate\Support\Carbon;

/**
 * Simulates Giglogistics hub verification + last-mile delivery for local dev.
 */
class FakeGiglogisticsGateway implements GiglogisticsGateway
{
    /** @var array<string, array<string, mixed>> */
    private array $shipments = [];

    public function __construct(private readonly HubVerificationService $hubVerification) {}

    public function quote(CreateShipmentRequest $request): ShipmentQuote
    {
        return new ShipmentQuote(
            feeMinor: $this->estimateFee($request->weightGrams),
            currency: 'NGN',
            estimatedDays: 3,
            carrierLabel: 'Giglogistics',
        );
    }

    public function createShipment(CreateShipmentRequest $request): ShipmentResponse
    {
        $externalId = 'GIG-'.strtoupper(substr(md5($request->reference), 0, 10));
        $tracking = 'GL'.strtoupper(substr(md5($externalId), 0, 12));
        $dropoff = 'RT'.strtoupper(substr(md5($request->reference.'drop'), 0, 8));

        $this->shipments[$externalId] = [
            'created_at' => now(),
            'weight_grams' => $request->weightGrams,
            'destination' => $request->destination,
            'listing_snapshot' => $request->listingSnapshot,
            'simulate_fail' => $request->simulateVerificationFail,
        ];

        return new ShipmentResponse(
            externalId: $externalId,
            trackingNumber: $tracking,
            dropoffCode: $dropoff,
            hubName: (string) config('reton.physical.default_hub_name', 'Giglogistics Verification Hub - Lekki'),
            hubAddress: (array) config('reton.physical.default_hub_address', [
                'line1' => '12 Admiralty Way',
                'city' => 'Lekki',
                'state' => 'Lagos',
                'phone' => '+234 700 GIG LOG',
            ]),
            feeMinor: $this->estimateFee($request->weightGrams),
            currency: 'NGN',
            estimatedDays: 3,
        );
    }

    public function getStatus(string $externalId): ShipmentStatusResponse
    {
        $record = $this->shipments[$externalId] ?? null;

        if ($record === null) {
            return new ShipmentStatusResponse(
                externalId: $externalId,
                trackingNumber: 'UNKNOWN',
                status: ShipmentStatus::Failed,
                events: [],
            );
        }

        $created = $record['created_at'];
        assert($created instanceof Carbon);
        $step = (int) config('services.giglogistics.fake_advance_minutes', 1);

        if ($step === 0) {
            $record['poll_count'] = ((int) ($record['poll_count'] ?? 0)) + 1;
            $this->shipments[$externalId] = $record;
            $status = $this->statusForPollCount($record['poll_count'], (bool) ($record['simulate_fail'] ?? false));
        } else {
            $minutes = $created->diffInMinutes(now());

            $status = match (true) {
                $minutes >= $step * 7 => ShipmentStatus::Delivered,
                $minutes >= $step * 6 => ShipmentStatus::OutForDelivery,
                $minutes >= $step * 5 => ShipmentStatus::InTransit,
                $minutes >= $step * 4 => ($record['simulate_fail'] ?? false)
                    ? ShipmentStatus::VerificationFailed
                    : ShipmentStatus::VerificationPassed,
                $minutes >= $step * 3 => ShipmentStatus::Verifying,
                $minutes >= $step * 2 => ShipmentStatus::AtHub,
                $minutes >= $step => ShipmentStatus::AtHub,
                default => ShipmentStatus::AwaitingDropoff,
            };
        }

        $hubStatus = null;
        $hubScore = null;
        $report = null;

        if (in_array($status, [ShipmentStatus::VerificationPassed, ShipmentStatus::VerificationFailed, ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery, ShipmentStatus::Delivered], true)) {
            $snapshot = (array) ($record['listing_snapshot'] ?? []);
            $findings = $this->hubFindings($snapshot, (bool) ($record['simulate_fail'] ?? false));
            $eval = $this->hubVerification->evaluate($snapshot, $findings);
            $hubStatus = $eval['status'];
            $hubScore = $eval['score'];
            $report = $eval['report'];

            if ($status === ShipmentStatus::VerificationFailed) {
                $hubStatus = HubVerificationStatus::Failed;
            }
        } elseif ($status === ShipmentStatus::Verifying) {
            $hubStatus = HubVerificationStatus::Pending;
        }

        return new ShipmentStatusResponse(
            externalId: $externalId,
            trackingNumber: 'GL'.strtoupper(substr(md5($externalId), 0, 12)),
            status: $status,
            events: $this->buildEvents($created, $status, $step),
            hubVerificationStatus: $hubStatus,
            hubVerificationScore: $hubScore,
            verificationReport: $report,
            podReference: $status === ShipmentStatus::Delivered ? 'POD-'.substr($externalId, -6) : null,
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{weight_grams: mixed, condition: mixed, brand: mixed, detail: mixed, item_label: string, notes: string}
     */
    private function hubFindings(array $snapshot, bool $fail): array
    {
        if ($fail) {
            return [
                'weight_grams' => ((int) ($snapshot['weight_grams'] ?? 500)) + 500,
                'condition' => 'fair',
                'brand' => 'Unknown',
                'detail' => 'Mismatch',
                'item_label' => 'Wrong item',
                'notes' => 'Item does not match Reton listing snapshot.',
            ];
        }

        $specs = (array) ($snapshot['specs'] ?? []);

        return [
            'weight_grams' => $snapshot['weight_grams'] ?? 500,
            'condition' => $snapshot['condition'] ?? 'good',
            'brand' => $specs['brand'] ?? '',
            'detail' => $specs['detail'] ?? '',
            'item_label' => (string) ($snapshot['title'] ?? 'Item'),
            'notes' => 'Matches locked Reton order description.',
        ];
    }

    private function estimateFee(int $weightGrams): int
    {
        $kg = max(1, (int) ceil($weightGrams / 1000));

        return 1_500_00 + ($kg * 350_00);
    }

    /**
     * @return list<array{status: string, at: string, note: string}>
     */
    private function buildEvents(Carbon $created, ShipmentStatus $current, int $step): array
    {
        $stages = [
            ShipmentStatus::AwaitingDropoff,
            ShipmentStatus::AtHub,
            ShipmentStatus::Verifying,
            ShipmentStatus::VerificationPassed,
            ShipmentStatus::InTransit,
            ShipmentStatus::OutForDelivery,
            ShipmentStatus::Delivered,
        ];

        if ($current === ShipmentStatus::VerificationFailed) {
            $stages[3] = ShipmentStatus::VerificationFailed;
        }

        $events = [];
        $i = 0;

        foreach ($stages as $stage) {
            if ($this->stageIndex($stage) > $this->stageIndex($current)) {
                break;
            }

            $events[] = [
                'status' => $stage->value,
                'at' => $created->copy()->addMinutes($step * $i)->toIso8601String(),
                'note' => match ($stage) {
                    ShipmentStatus::AwaitingDropoff => 'Drop-off scheduled - seller brings item to Giglogistics hub.',
                    ShipmentStatus::AtHub => 'Package received at verification hub.',
                    ShipmentStatus::Verifying => 'Inspecting item against Reton locked description.',
                    ShipmentStatus::VerificationPassed => 'Verified - matches buyer order snapshot.',
                    ShipmentStatus::VerificationFailed => 'Failed verification - does not match listing.',
                    ShipmentStatus::InTransit => 'Released to courier for buyer delivery.',
                    ShipmentStatus::OutForDelivery => 'With last-mile courier.',
                    ShipmentStatus::Delivered => 'Delivered to buyer address.',
                },
            ];
            $i++;
        }

        return $events;
    }

    private function statusForPollCount(int $pollCount, bool $simulateFail): ShipmentStatus
    {
        return match ($pollCount) {
            1 => ShipmentStatus::AtHub,
            2 => ShipmentStatus::Verifying,
            3 => $simulateFail ? ShipmentStatus::VerificationFailed : ShipmentStatus::VerificationPassed,
            4 => ShipmentStatus::InTransit,
            5 => ShipmentStatus::OutForDelivery,
            default => ShipmentStatus::Delivered,
        };
    }

    private function stageIndex(ShipmentStatus $status): int
    {
        return match ($status) {
            ShipmentStatus::AwaitingDropoff, ShipmentStatus::PendingPickup => 0,
            ShipmentStatus::AtHub => 1,
            ShipmentStatus::Verifying => 2,
            ShipmentStatus::VerificationPassed, ShipmentStatus::VerificationFailed => 3,
            ShipmentStatus::InTransit, ShipmentStatus::PickedUp => 4,
            ShipmentStatus::OutForDelivery => 5,
            ShipmentStatus::Delivered => 6,
            default => -1,
        };
    }
}
