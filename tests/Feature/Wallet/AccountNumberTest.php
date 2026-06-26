<?php

declare(strict_types=1);

use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns a unique 10-digit account number when a wallet is opened', function () {
    $wallet = app(WalletService::class)->open(User::factory()->create(), 'NGN');

    expect($wallet->account_number)->toMatch('/^\d{10}$/');
});

it('gives different wallets different account numbers', function () {
    $a = app(WalletService::class)->open(User::factory()->create(), 'NGN');
    $b = app(WalletService::class)->open(User::factory()->create(), 'NGN');

    expect($a->account_number)->not->toBe($b->account_number);
});

it('exposes the account number on the wallet API', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    $this->actingAs($user)->getJson("/api/v1/wallets/{$wallet->id}")
        ->assertOk()
        ->assertJsonPath('data.account_number', $wallet->account_number);
});

it('resolves an account number to its holder via lookup', function () {
    $me = User::factory()->create();
    $recipient = User::factory()->create(['name' => 'Ada Lovelace']);
    app(WalletService::class)->open($me, 'NGN');
    $recipientWallet = app(WalletService::class)->open($recipient, 'NGN');

    $this->actingAs($me)
        ->getJson('/api/v1/wallets/lookup?account_number='.$recipientWallet->account_number)
        ->assertOk()
        ->assertJsonPath('data.account_name', 'Ada Lovelace')
        ->assertJsonPath('data.wallet_id', $recipientWallet->id);
});

it('returns 404 for an unknown account number', function () {
    $me = User::factory()->create();
    app(WalletService::class)->open($me, 'NGN');

    $this->actingAs($me)
        ->getJson('/api/v1/wallets/lookup?account_number=0000000001')
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

it('rejects a malformed account number', function () {
    $me = User::factory()->create();
    app(WalletService::class)->open($me, 'NGN');

    $this->actingAs($me)
        ->getJson('/api/v1/wallets/lookup?account_number=abc')
        ->assertStatus(422);
});
