<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum StaticAccountStatus: string
{
    case PendingOtp = 'pending_otp';
    case Active = 'active';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
