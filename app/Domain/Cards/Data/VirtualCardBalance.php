<?php

declare(strict_types=1);

namespace App\Domain\Cards\Data;

final readonly class VirtualCardBalance
{
    public function __construct(
        public int $availableMinor,
        public string $currency = 'NGN',
    ) {}
}
