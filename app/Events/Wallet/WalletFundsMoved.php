<?php

declare(strict_types=1);

namespace App\Events\Wallet;

use App\Domain\Ledger\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Support\Money\Money;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a customer wallet credit or debit posts successfully.
 */
class WalletFundsMoved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Wallet $wallet,
        public Transaction $transaction,
        public string $direction,
        public Money $amount,
    ) {}

    public function isCredit(): bool
    {
        return $this->direction === 'credit';
    }

    public function isDebit(): bool
    {
        return $this->direction === 'debit';
    }
}
