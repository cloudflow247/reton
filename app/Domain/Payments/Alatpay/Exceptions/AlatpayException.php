<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Exceptions;

use RuntimeException;

final class AlatpayException extends RuntimeException
{
    public static function requestFailed(string $operation, int $status): self
    {
        return new self("AlatPay request [{$operation}] failed with HTTP {$status}.");
    }
}
