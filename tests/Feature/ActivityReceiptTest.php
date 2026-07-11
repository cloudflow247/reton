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

it('forbids viewing another users ledger entry', function () {
    [, $wallet] = readyUserWithWallet([], 100_00);
    [$intruder] = readyUserWithWallet();

    $entryId = $wallet->fresh()->ledgerAccount->entries()->latest('created_at')->value('id');

    $this->actingAs($intruder)->get('/activity/'.$entryId)
        ->assertNotFound();
});

it('aligns dashboard activity rows with money-flow totals', function () {
    [$user, $wallet] = readyUserWithWallet();

    foreach ([100_00, 200_00, 93_00, 100_00, 150_00, 100_00] as $amount) {
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

it('exposes available escrow and ledger total consistently on the wallet', function () {
    [, $wallet] = readyUserWithWallet([], 743_00);
    $wallet->forceFill(['held_balance' => 200_00])->save();

    expect($wallet->fresh()->availableMinor())->toBe(543_00)
        ->and($wallet->fresh()->heldMinor())->toBe(200_00)
        ->and($wallet->fresh()->ledgerMinor())->toBe(743_00)
        ->and($wallet->fresh()->availableMinor() + $wallet->fresh()->heldMinor())->toBe($wallet->fresh()->ledgerMinor());
});
