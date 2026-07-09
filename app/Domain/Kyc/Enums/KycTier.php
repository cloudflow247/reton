<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Enums;

enum KycTier: int
{
    case Tier1 = 1;
    case Tier2 = 2;
    case Tier3 = 3;

    public function label(): string
    {
        return match ($this) {
            self::Tier1 => 'Basic',
            self::Tier2 => 'BVN verified',
            self::Tier3 => 'Full KYC',
        };
    }

    public function isAtLeast(self $required): bool
    {
        return $this->value >= $required->value;
    }
}
