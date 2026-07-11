<?php

declare(strict_types=1);

use App\Domain\Wallet\Services\WalletService;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows a statement receipt the wallet owner can open', function () {
    [$user, $wallet] = readyUserWithWallet([], 250_00);

    $entryId = $wallet->fresh()->ledgerAccount->entries()->latest('created_at')->value('id');

    $this->actingAs($user)->get('/activity/'.$entryId)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Activity/Show')
            ->where('entry.id', $entryId)
            ->where('entry.amount', 250_00)
            ->where('entry.direction', 'credit')
            ->has('receipt.app')
            ->has('wallet.account_number'));
});

it('renders a transfer receipt even when the hold row is missing', function () {
    [$sender, $senderWallet] = readyUserWithWallet([], 500_00);
    [, $receiverWallet] = readyUserWithWallet();

    $transfer = app(\App\Domain\Transfers\Services\TransferService::class)->sendNormal(
        $sender,
        $senderWallet,
        $receiverWallet,
        Money::of(100_00, 'NGN'),
        'Receipt test',
        null,
    );

    // Instant transfers may have no hold — resource must not 500 on null hold.
    $transfer->hold()?->delete();

    $entryId = \App\Domain\Ledger\Models\LedgerEntry::query()
        ->where('transaction_id', $transfer->transaction_id)
        ->where('ledger_account_id', $senderWallet->ledger_account_id)
        ->value('id');

    expect($entryId)->not->toBeNull();

    $this->actingAs($sender)->get('/activity/'.$entryId)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Activity/Show')
            ->where('transfer.reference', $transfer->reference)
            ->missing('transfer.hold'));
});

it('forbids viewing another users ledger entry', function () {
    [, $wallet] = readyUserWithWallet([], 100_00);
    [$intruder] = readyUserWithWallet();

    $entryId = $wallet->fresh()->ledgerAccount->entries()->latest('created_at')->value('id');

    $this->actingAs($intruder)->get('/activity/'.$entryId)
        ->assertNotFound();
});

it('aligns dashboard activity rows with money-flow totals', function () {
    [$user, $wallet] = readyUserWithWallet();

    foreach ([100_00, 200_00, 93_00, 100_00, 150_00, 100_00] as $i => $amount) {
        $this->travel($i + 1)->seconds();
        app(WalletService::class)->fund($wallet->fresh(), Money::of($amount, 'NGN'));
    }

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('activity', 5)
            ->where('activityFlow.count', 5)
            ->where('activityFlow.inflow', 643_00)
            ->where('auth.wallets.0.balance', 743_00)
            ->where('auth.wallets.0.available_balance', 743_00)
            ->where('auth.wallets.0.held_balance', 0));
});

it('shows sender and receiver parties on a Reton transfer receipt', function () {
    [$sender, $senderWallet] = readyUserWithWallet([], 500_00);
    [$receiver, $receiverWallet] = readyUserWithWallet();
    $sender->forceFill(['name' => 'Ada Sender'])->save();
    $receiver->forceFill(['name' => 'Bola Receiver'])->save();

    $transfer = app(\App\Domain\Transfers\Services\TransferService::class)->sendNormal(
        $sender,
        $senderWallet,
        $receiverWallet,
        Money::of(100_00, 'NGN'),
        'Party receipt',
        'receipt-parties-1',
    );

    $entryId = \App\Domain\Ledger\Models\LedgerEntry::query()
        ->where('transaction_id', $transfer->transaction_id)
        ->where('ledger_account_id', $senderWallet->ledger_account_id)
        ->value('id');

    $this->actingAs($sender)->get('/activity/'.$entryId)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Activity/Show')
            ->where('parties.channel', 'reton_transfer')
            ->where('parties.from.name', 'Ada Sender')
            ->where('parties.from.reton_id', $senderWallet->fresh()->account_number)
            ->where('parties.to.name', 'Bola Receiver')
            ->where('parties.to.reton_id', $receiverWallet->fresh()->account_number));
});

it('shows bank funding parties on a dedicated-account deposit receipt', function () {
    config()->set('services.alatpay.business_bvn', '22222222222');
    $gateway = new \App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
    $this->app->instance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class, $gateway);

    [$user, $wallet] = readyUserWithWallet();
    ensureVerifiedBvn($user);
    $svc = app(\App\Domain\Payments\Services\StaticAccountService::class);
    $account = $svc->provision($user, $wallet, \App\Domain\Payments\Enums\StaticWalletType::Individual, '12345678901');
    $account = $svc->verify($account, '123456');
    $account->forceFill(['bank_name' => 'Wema Bank'])->save();

    $gateway->markStaticFunded($account->account_number, 80.00, 'txn-receipt-bank');
    $svc->poll($account->fresh());

    $deposit = \App\Domain\Payments\Models\Deposit::query()
        ->where('provider_reference', 'txn-receipt-bank')
        ->firstOrFail();

    $entryId = \App\Domain\Ledger\Models\LedgerEntry::query()
        ->where('transaction_id', $deposit->transaction_id)
        ->where('ledger_account_id', $wallet->ledger_account_id)
        ->value('id');

    $this->actingAs($user)->get('/activity/'.$entryId)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Activity/Show')
            ->where('parties.channel', 'bank_deposit')
            ->where('parties.from.bank_name', 'Wema Bank')
            ->where('parties.to.reton_id', $wallet->fresh()->account_number));
});

it('exposes available escrow and ledger total consistently on the wallet', function () {
    [, $wallet] = readyUserWithWallet([], 743_00);
    $wallet->forceFill(['held_balance' => 200_00])->save();

    expect($wallet->fresh()->availableMinor())->toBe(543_00)
        ->and($wallet->fresh()->heldMinor())->toBe(200_00)
        ->and($wallet->fresh()->ledgerMinor())->toBe(743_00)
        ->and($wallet->fresh()->availableMinor() + $wallet->fresh()->heldMinor())->toBe($wallet->fresh()->ledgerMinor());
});
