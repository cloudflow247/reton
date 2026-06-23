<?php

declare(strict_types=1);

namespace App\Domain\Callback\Enums;

enum CallbackResolution: string
{
    case Release = 'release';
    case Refund = 'refund';

    public function toStatus(): CallbackStatus
    {
        return $this === self::Refund ? CallbackStatus::Refunded : CallbackStatus::Released;
    }
}
