<?php

declare(strict_types=1);

namespace App\Domain\Payments\Paystack\Exceptions;

use RuntimeException;

final class PaystackException extends RuntimeException
{
    public static function requestFailed(string $operation, int $status): self
    {
        return new self("Paystack {$operation} failed with HTTP {$status}.", $status);
    }
}
