<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum PaymentRequestStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }
}
