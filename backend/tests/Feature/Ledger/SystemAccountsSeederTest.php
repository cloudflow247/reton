<?php

declare(strict_types=1);

use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Models\LedgerAccount;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('materialises the full house chart for every configured currency', function () {
    config(['reton.currencies' => ['NGN', 'USD']]);

    $this->seed(SystemAccountsSeeder::class);

    $expected = count(SystemAccount::cases()) * 2;

    expect(LedgerAccount::where('is_system', true)->count())->toBe($expected)
        ->and(LedgerAccount::where('code', 'system:cash:NGN')->first()?->type)->toBe(AccountType::Asset)
        ->and(LedgerAccount::where('code', 'system:settlement_payable:USD')->first()?->type)->toBe(AccountType::Liability);
});

it('is idempotent — reseeding creates no duplicates', function () {
    config(['reton.currencies' => ['NGN']]);

    $this->seed(SystemAccountsSeeder::class);
    $this->seed(SystemAccountsSeeder::class);

    expect(LedgerAccount::where('is_system', true)->count())
        ->toBe(count(SystemAccount::cases()));
});

it('seeds valid accounts through the top-level DatabaseSeeder', function () {
    // Guards against muting model events during seeding, which would skip the
    // UUID-assignment hook and produce accounts with null primary keys.
    $this->seed();

    $accounts = LedgerAccount::where('is_system', true)->get();

    expect($accounts)->not->toBeEmpty()
        ->and($accounts->whereNull('id'))->toBeEmpty();
});
