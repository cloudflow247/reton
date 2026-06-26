<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Data;

use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Support\Money\Money;

final readonly class EntryDraft
{
    public string $accountId;

    public function __construct(
        LedgerAccount|string $account,
        public EntryDirection $direction,
        public Money $amount,
    ) {
        $this->accountId = $account instanceof LedgerAccount ? $account->getKey() : $account;
    }
}
