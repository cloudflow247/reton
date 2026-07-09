<?php

declare(strict_types=1);

namespace App\Domain\Support\Enums;

enum SupportTicketStatus: string
{
    case Open = 'open';
    case Escalated = 'escalated';
    case Resolved = 'resolved';

    public function isOpen(): bool
    {
        return $this === self::Open || $this === self::Escalated;
    }
}
