<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum StaticWalletType: string
{
    case Individual = 'individual';
    case Collection = 'collection';

    public function providerCode(): int
    {
        return match ($this) {
            self::Individual => 1,
            self::Collection => 2,
        };
    }
}
