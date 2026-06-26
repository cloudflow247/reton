<?php

declare(strict_types=1);

namespace App\Domain\Bills\Remita\Exceptions;

use RuntimeException;

final class BillProviderException extends RuntimeException
{
    public static function requestFailed(string $operation, int $status): self
    {
        return new self("Bill provider request [{$operation}] failed with HTTP {$status}.");
    }

    public static function unknownRrr(string $rrr): self
    {
        return new self("No bill found for RRR [{$rrr}].");
    }
}
