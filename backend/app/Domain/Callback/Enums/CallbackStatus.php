<?php

declare(strict_types=1);

namespace App\Domain\Callback\Enums;

enum CallbackStatus: string
{
    case Pending = 'pending';
    case Escalated = 'escalated';
    case Released = 'released';
    case Refunded = 'refunded';

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Escalated;
    }

    public function isResolved(): bool
    {
        return $this === self::Released || $this === self::Refunded;
    }
}
