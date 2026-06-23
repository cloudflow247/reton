<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum DepositStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
}
