<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Enums;

enum DigitalOrderStatus: string
{
    case PaidHeld = 'paid_held';
    case AwaitingVerification = 'awaiting_verification';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Disputed = 'disputed';
    case Refunded = 'refunded';
}
