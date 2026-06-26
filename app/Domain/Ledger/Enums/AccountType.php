<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    /**
     * The side on which this account type increases.
     *
     * Assets and expenses carry a natural debit balance; liabilities, equity
     * and revenue carry a natural credit balance.
     */
    public function normalBalance(): EntryDirection
    {
        return match ($this) {
            self::Asset, self::Expense => EntryDirection::Debit,
            self::Liability, self::Equity, self::Revenue => EntryDirection::Credit,
        };
    }
}
