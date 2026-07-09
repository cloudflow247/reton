<?php

declare(strict_types=1);

namespace App\Domain\Logistics\Giglogistics\Data;

final readonly class CreateShipmentRequest
{
    /**
     * @param  array<string, string>  $origin
     * @param  array<string, string>  $destination
     * @param  array<string, mixed>  $listingSnapshot
     */
    public function __construct(
        public string $reference,
        public int $weightGrams,
        public array $origin,
        public array $destination,
        public string $description,
        public array $listingSnapshot = [],
        public bool $simulateVerificationFail = false,
    ) {}
}
