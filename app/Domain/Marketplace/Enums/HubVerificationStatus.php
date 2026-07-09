<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Enums;

enum HubVerificationStatus: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting hub check',
            self::Passed => 'Verified at hub',
            self::Failed => 'Failed hub check',
        };
    }
}
