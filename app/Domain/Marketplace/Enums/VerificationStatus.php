<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Enums;

enum VerificationStatus: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Flagged = 'flagged';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Passed => 'Verified',
            self::Flagged => 'Needs attention',
        };
    }
}
