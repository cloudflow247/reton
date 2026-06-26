<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Posted = 'posted';
    case Reversed = 'reversed';
    case Failed = 'failed';
}
