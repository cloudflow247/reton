<?php

declare(strict_types=1);

namespace App\Domain\Bills\Enums;

enum BillStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
