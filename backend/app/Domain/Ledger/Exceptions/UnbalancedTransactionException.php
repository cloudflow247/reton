<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Exceptions;

use DomainException;

final class UnbalancedTransactionException extends DomainException
{
    public static function dueToSides(int $debits, int $credits): self
    {
        return new self("Transaction is unbalanced: debits={$debits} credits={$credits}.");
    }

    public static function tooFewEntries(int $count): self
    {
        return new self("A posting requires at least two entries; {$count} given.");
    }

    public static function nonPositiveAmount(): self
    {
        return new self('Every ledger entry must carry a strictly positive amount.');
    }
}
