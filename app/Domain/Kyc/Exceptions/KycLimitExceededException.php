<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Exceptions;

use RuntimeException;

class KycLimitExceededException extends RuntimeException
{
    public static function singleTransaction(int $tier, int $limitMinor): self
    {
        $amount = number_format($limitMinor / 100, 2);

        return new self("Tier {$tier} limit: a single transaction cannot exceed NGN {$amount}. Upgrade your KYC to increase limits.");
    }

    public static function dailyInflow(int $tier, int $limitMinor): self
    {
        return new self("Tier {$tier} limit: you have reached today's funding limit. Upgrade your KYC or try again tomorrow.");
    }

    public static function walletBalance(int $tier, int $limitMinor): self
    {
        $amount = number_format($limitMinor / 100, 2);

        return new self("Tier {$tier} limit: your wallet cannot hold more than NGN {$amount}. Upgrade your KYC to raise the cap.");
    }

    public static function tierRequired(int $requiredTier): self
    {
        return new self("Complete Tier {$requiredTier} verification to use this feature.");
    }
}
