<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Services\SystemAccountResolver;
use Illuminate\Database\Seeder;

/**
 * Materialises Reton's house chart of accounts, once per supported currency.
 *
 * Idempotent: backed by firstOrCreate on the account code, so it is safe to run
 * on every deploy.
 */
class SystemAccountsSeeder extends Seeder
{
    public function __construct(private readonly SystemAccountResolver $resolver) {}

    public function run(): void
    {
        /** @var list<string> $currencies */
        $currencies = config('reton.currencies', ['NGN']);

        foreach ($currencies as $currency) {
            foreach (SystemAccount::cases() as $slot) {
                $this->resolver->resolve($slot, $currency);
            }
        }
    }
}
