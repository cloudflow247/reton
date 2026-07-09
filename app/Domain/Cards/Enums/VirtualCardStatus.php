<?php

declare(strict_types=1);

namespace App\Domain\Cards\Enums;

enum VirtualCardStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Blocked = 'blocked';

    public function isBlocked(): bool
    {
        return $this === self::Blocked;
    }
}
