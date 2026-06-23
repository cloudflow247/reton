<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Enums;

enum HoldStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Refunded = 'refunded';
    case Expired = 'expired';
}
