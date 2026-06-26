<?php

declare(strict_types=1);

use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function wallets(): WalletService
{
    return app(WalletService::class);
}

function naira(int $minor): Money
{
    return Money::of($minor, 'NGN');
}

it('opens a wallet backed by a liability ledger account', function () {
    $user = User::factory()->create();

    $wallet = wallets()->open($user, 'NGN');

    expect($wallet)->toBeInstanceOf(Wallet::class)
        ->and($wallet->currency)->toBe('NGN')
        ->and($wallet->balance)->toBe(0)
        ->and($wallet->ledgerAccount->type)->toBe(AccountType::Liability)
        ->and($wallet->ledgerAccount->currency)->toBe('NGN')
        ->and($wallet->owner->is($user))->toBeTrue();
});

it('returns the same wallet when opening twice for one owner and currency', function () {
    $user = User::factory()->create();

    $first = wallets()->open($user, 'NGN');
    $second = wallets()->open($user, 'NGN');

    expect($second->id)->toBe($first->id)
        ->and(Wallet::count())->toBe(1);
});

it('funds a wallet and increases its balance through the ledger', function () {
    $user = User::factory()->create();
    $wallet = wallets()->open($user, 'NGN');

    $tx = wallets()->fund($wallet, naira(500_00));

    expect($tx)->toBeInstanceOf(Transaction::class)
        ->and($wallet->fresh()->balance)->toBe(50000);

    // Funding draws from the system cash account.
    $cash = app(SystemAccountResolver::class)->resolve(SystemAccount::Cash, 'NGN');
    expect($cash->fresh()->posted_debits)->toBe(50000);
});

it('withdraws from a wallet and reduces its balance', function () {
    $user = User::factory()->create();
    $wallet = wallets()->open($user, 'NGN');
    wallets()->fund($wallet, naira(500_00));

    wallets()->withdraw($wallet, naira(200_00));

    expect($wallet->fresh()->balance)->toBe(30000);
});

it('refuses to withdraw more than the available balance', function () {
    $user = User::factory()->create();
    $wallet = wallets()->open($user, 'NGN');
    wallets()->fund($wallet, naira(100_00));

    wallets()->withdraw($wallet, naira(150_00));
})->throws(InsufficientFundsException::class);

it('treats held funds as unavailable when withdrawing', function () {
    $user = User::factory()->create();
    $wallet = wallets()->open($user, 'NGN');
    wallets()->fund($wallet, naira(100_00));

    // Simulate a callback/recovery hold over part of the balance. held_balance
    // is guarded from mass assignment (only the holds mechanism mutates it), so
    // force the value to stand in for a real hold.
    $wallet->forceFill(['held_balance' => 80_00])->save();

    wallets()->withdraw($wallet, naira(50_00));
})->throws(InsufficientFundsException::class);

it('transfers funds between two wallets without creating money', function () {
    $sender = wallets()->open(User::factory()->create(), 'NGN');
    $receiver = wallets()->open(User::factory()->create(), 'NGN');
    wallets()->fund($sender, naira(300_00));

    wallets()->transfer($sender, $receiver, naira(120_00));

    expect($sender->fresh()->balance)->toBe(18000)
        ->and($receiver->fresh()->balance)->toBe(12000);
});

it('refuses to transfer more than the sender can afford', function () {
    $sender = wallets()->open(User::factory()->create(), 'NGN');
    $receiver = wallets()->open(User::factory()->create(), 'NGN');
    wallets()->fund($sender, naira(50_00));

    wallets()->transfer($sender, $receiver, naira(60_00));
})->throws(InsufficientFundsException::class);

it('is idempotent when funding with the same key', function () {
    $user = User::factory()->create();
    $wallet = wallets()->open($user, 'NGN');

    wallets()->fund($wallet, naira(100_00), 'fund-key-1');
    wallets()->fund($wallet, naira(100_00), 'fund-key-1');

    expect($wallet->fresh()->balance)->toBe(10000)
        ->and(Transaction::count())->toBe(1);
});
