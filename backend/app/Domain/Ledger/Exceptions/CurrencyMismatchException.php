<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Exceptions;

use DomainException;

final class CurrencyMismatchException extends DomainException
{
    public static function withinPosting(string $expected, string $found): self
    {
        return new self("All entries in a posting must share one currency; expected {$expected}, found {$found}.");
    }

    public static function accountMismatch(string $accountCurrency, string $entryCurrency): self
    {
        return new self("Entry currency {$entryCurrency} does not match account currency {$accountCurrency}.");
    }
}
