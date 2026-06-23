<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Enums;

enum TransferStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Held = 'held';
    case Refunded = 'refunded';
    case Failed = 'failed';
}
