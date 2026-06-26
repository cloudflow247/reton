<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

/**
 * The canonical chart of Reton's own (house) accounts.
 *
 * Each slot is materialised once per currency as a concrete ledger account.
 * User wallets are liability accounts that sit outside this enum; these are the
 * counterparties Reton books against when money enters, leaves or is earned.
 */
enum SystemAccount: string
{
    /** Money Reton holds at its banks / payment service providers. */
    case Cash = 'cash';

    /** Fee income earned by Reton. */
    case FeesRevenue = 'fees_revenue';

    /** Amounts owed to external banks for outbound settlements. */
    case SettlementPayable = 'settlement_payable';

    /** Clearing account for in-flight or unreconciled funds. */
    case Suspense = 'suspense';

    /** Escrow holding funds for protected (callback) transfers in flight. */
    case CallbackHolds = 'callback_holds';

    public function type(): AccountType
    {
        return match ($this) {
            self::Cash, self::Suspense => AccountType::Asset,
            self::FeesRevenue => AccountType::Revenue,
            self::SettlementPayable, self::CallbackHolds => AccountType::Liability,
        };
    }

    public function displayName(): string
    {
        return match ($this) {
            self::Cash => 'House Cash',
            self::FeesRevenue => 'Fees Revenue',
            self::SettlementPayable => 'Settlement Payable',
            self::Suspense => 'Suspense / Clearing',
            self::CallbackHolds => 'Callback Holds (Escrow)',
        };
    }

    public function code(string $currency): string
    {
        return 'system:'.$this->value.':'.strtoupper($currency);
    }
}
