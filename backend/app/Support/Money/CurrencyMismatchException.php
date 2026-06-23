<?php

declare(strict_types=1);

namespace App\Support\Money;

use InvalidArgumentException;

final class CurrencyMismatchException extends InvalidArgumentException
{
    public static function between(string $left, string $right): self
    {
        return new self("Cannot operate across currencies: {$left} and {$right}.");
    }
}
