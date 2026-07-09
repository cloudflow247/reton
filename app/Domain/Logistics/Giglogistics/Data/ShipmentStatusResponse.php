<?php

declare(strict_types=1);

namespace App\Domain\Logistics\Giglogistics\Data;

use App\Domain\Marketplace\Enums\HubVerificationStatus;
use App\Domain\Marketplace\Enums\ShipmentStatus;

final readonly class ShipmentStatusResponse
{
    /**
     * @param  list<array{status: string, at: string, note: string, event_id?: string}>  $events
     * @param  array<string, mixed>|null  $verificationReport
     */
    public function __construct(
        public string $externalId,
        public string $trackingNumber,
        public ShipmentStatus $status,
        public array $events,
        public ?HubVerificationStatus $hubVerificationStatus = null,
        public ?int $hubVerificationScore = null,
        public ?array $verificationReport = null,
        public ?string $podReference = null,
    ) {}
}
