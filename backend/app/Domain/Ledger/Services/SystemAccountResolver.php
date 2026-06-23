<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Support\Money\Money;

/**
 * Resolves (and lazily materialises) Reton's house accounts per currency.
 */
class SystemAccountResolver
{
    public function resolve(SystemAccount $slot, string $currency): LedgerAccount
    {
        $currency = Money::zero($currency)->currency;

        return LedgerAccount::firstOrCreate(
            ['code' => $slot->code($currency)],
            [
                'name' => $slot->displayName().' ('.$currency.')',
                'type' => $slot->type(),
                'currency' => $currency,
                'is_system' => true,
            ],
        );
    }
}
