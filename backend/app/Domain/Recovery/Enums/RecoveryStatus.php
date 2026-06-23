<?php

declare(strict_types=1);

namespace App\Domain\Recovery\Enums;

enum RecoveryStatus: string
{
    case Held = 'held';
    case Escalated = 'escalated';
    case Returned = 'returned';
    case Declined = 'declined';

    public function isOpen(): bool
    {
        return $this === self::Held || $this === self::Escalated;
    }
}
