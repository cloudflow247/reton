<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
