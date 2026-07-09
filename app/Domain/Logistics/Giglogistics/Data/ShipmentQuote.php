<?php

declare(strict_types=1);

namespace App\Domain\Logistics\Giglogistics\Data;

final readonly class ShipmentQuote
{
    public function __construct(
        public int $feeMinor,
        public string $currency,
        public int $estimatedDays,
        public string $carrierLabel,
    ) {}
}
