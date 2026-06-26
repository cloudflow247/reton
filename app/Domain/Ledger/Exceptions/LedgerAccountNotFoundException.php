<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Exceptions;

use DomainException;

final class LedgerAccountNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Ledger account [{$id}] does not exist.");
    }
}
