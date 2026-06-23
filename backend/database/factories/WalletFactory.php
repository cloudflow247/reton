<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        $currency = 'NGN';

        return [
            'ledger_account_id' => LedgerAccountFactory::new()->state([
                'type' => AccountType::Liability,
                'currency' => $currency,
            ]),
            'currency' => $currency,
            'status' => 'active',
        ];
    }

    public function configure(): static
    {
        // Keep the wallet's currency aligned with its backing ledger account.
        return $this->afterCreating(function (Wallet $wallet): void {
            $account = $wallet->ledgerAccount;

            if ($account instanceof LedgerAccount && $account->currency !== $wallet->currency) {
                $account->update(['currency' => $wallet->currency]);
            }
        });
    }
}
