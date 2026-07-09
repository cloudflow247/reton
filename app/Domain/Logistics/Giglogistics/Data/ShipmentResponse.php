<?php

declare(strict_types=1);

namespace App\Domain\Logistics\Giglogistics\Data;

final readonly class ShipmentResponse
{
    /**
     * @param  array<string, string>  $hubAddress
     */
    public function __construct(
        public string $externalId,
        public string $trackingNumber,
        public string $dropoffCode,
        public string $hubName,
        public array $hubAddress,
        public int $feeMinor,
        public string $currency,
        public int $estimatedDays,
    ) {}
}
