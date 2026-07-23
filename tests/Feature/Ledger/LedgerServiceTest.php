<?php

declare(strict_types=1);

use App\Domain\Ledger\Data\PostingDraft;
use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Exceptions\CurrencyMismatchException;
use App\Domain\Ledger\Exceptions\UnbalancedTransactionException;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Wallet\Models\Wallet;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ledger(): LedgerService
{
    return app(LedgerService::class);
}

function ngn(int $minor): Money
{
    return Money::of($minor, 'NGN');
}

beforeEach(function () {
    $this->cash = LedgerAccount::factory()->system()->asset()->create([
        'code' => 'system:cash',
        'currency' => 'NGN',
    ]);
});

it('posts a balanced funding transaction and projects the wallet balance', function () {
    $wallet = Wallet::factory()->create(['currency' => 'NGN']);

    $tx = ledger()->post(
        PostingDraft::for(TransactionType::WalletFunding)
            ->describedAs('Wallet funding')
            ->debit($this->cash, ngn(100_00))
            ->credit($wallet->ledgerAccount, ngn(100_00))
    );

    expect($tx)->toBeInstanceOf(Transaction::class)
        ->and($tx->amount)->toBe(10000)
        ->and($tx->entries)->toHaveCount(2)
        ->and($wallet->fresh()->balance)->toBe(10000);
});

it('keeps the global trial balance at zero (sum debits == sum credits)', function () {
    $wallet = Wallet::factory()->create(['currency' => 'NGN']);

    ledger()->post(
        PostingDraft::for(TransactionType::WalletFunding)
            ->describedAs('Wallet funding')
            ->debit($this->cash, ngn(250_00))
            ->credit($wallet->ledgerAccount, ngn(250_00))
    );

    $debits = LedgerEntry::where('direction', EntryDirection::Debit)->sum('amount');
    $credits = LedgerEntry::where('direction', EntryDirection::Credit)->sum('amount');

    expect((int) $debits)->toBe(25000)
        ->and((int) $credits)->toBe(25000);
});

it('moves funds between two wallets without creating or destroying money', function () {
    $sender = Wallet::factory()->create(['currency' => 'NGN']);
    $receiver = Wallet::factory()->create(['currency' => 'NGN']);

    ledger()->post(
        PostingDraft::for(TransactionType::WalletFunding)
            ->describedAs('Seed sender')
            ->debit($this->cash, ngn(500_00))
            ->credit($sender->ledgerAccount, ngn(500_00))
    );

    ledger()->post(
        PostingDraft::for(TransactionType::InternalTransfer)
            ->describedAs('A pays B')
            ->debit($sender->ledgerAccount, ngn(200_00))
            ->credit($receiver->ledgerAccount, ngn(200_00))
    );

    expect($sender->fresh()->balance)->toBe(30000)
        ->and($receiver->fresh()->balance)->toBe(20000);
});

it('rejects an unbalanced transaction', function () {
    $wallet = Wallet::factory()->create(['currency' => 'NGN']);

    ledger()->post(
        PostingDraft::for(TransactionType::WalletFunding)
            ->describedAs('Broken')
            ->debit($this->cash, ngn(100_00))
            ->credit($wallet->ledgerAccount, ngn(90_00))
    );
})->throws(UnbalancedTransactionException::class);

it('rejects a single-sided transaction', function () {
    ledger()->post(
        PostingDraft::for(TransactionType::WalletFunding)
            ->describedAs('Lonely')
            ->debit($this->cash, ngn(100_00))
    );
})->throws(UnbalancedTransactionException::class);

it('rejects entries spanning multiple currencies', function () {
    $usd = LedgerAccount::factory()->system()->asset()->create([
        'code' => 'system:cash_usd',
        'currency' => 'USD',
    ]);
    $wallet = Wallet::factory()->create(['currency' => 'NGN']);

    ledger()->post(
        PostingDraft::for(TransactionType::WalletFunding)
            ->describedAs('Mixed')
            ->debit($usd, Money::of(100_00, 'USD'))
            ->credit($wallet->ledgerAccount, ngn(100_00))
    );
})->throws(CurrencyMismatchException::class);

it('is idempotent - the same idempotency key never posts twice', function () {
    $wallet = Wallet::factory()->create(['currency' => 'NGN']);

    $draft = fn () => PostingDraft::for(TransactionType::WalletFunding)
        ->describedAs('Wallet funding')
        ->idempotentBy('idem-key-123')
        ->debit($this->cash, ngn(100_00))
        ->credit($wallet->ledgerAccount, ngn(100_00));

    $first = ledger()->post($draft());
    $second = ledger()->post($draft());

    expect($second->id)->toBe($first->id)
        ->and(Transaction::count())->toBe(1)
        ->and(LedgerEntry::count())->toBe(2)
        ->and($wallet->fresh()->balance)->toBe(10000);
});

it('generates a unique reference per transaction', function () {
    $wallet = Wallet::factory()->create(['currency' => 'NGN']);

    $tx = ledger()->post(
        PostingDraft::for(TransactionType::WalletFunding)
            ->describedAs('Wallet funding')
            ->debit($this->cash, ngn(100_00))
            ->credit($wallet->ledgerAccount, ngn(100_00))
    );

    expect($tx->reference)->not->toBeEmpty()
        ->and($tx->status->value)->toBe('posted')
        ->and($tx->posted_at)->not->toBeNull();
});

it('records entries as immutable - they cannot be updated', function () {
    $wallet = Wallet::factory()->create(['currency' => 'NGN']);

    $tx = ledger()->post(
        PostingDraft::for(TransactionType::WalletFunding)
            ->describedAs('Wallet funding')
            ->debit($this->cash, ngn(100_00))
            ->credit($wallet->ledgerAccount, ngn(100_00))
    );

    $entry = $tx->entries->first();
    $entry->amount = 999_99;
    $entry->save();
})->throws(RuntimeException::class);
