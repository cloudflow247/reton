<?php

declare(strict_types=1);

use App\Domain\Wallet\Services\WalletService;
use App\Domain\Wallet\Support\RetonId;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns a unique reton id when a wallet is opened', function () {
    $wallet = app(WalletService::class)->open(User::factory()->create(), 'NGN');

    expect($wallet->account_number)->toMatch('/^R\d{9}$/')
        ->and(RetonId::isValid($wallet->account_number))->toBeTrue();
});

it('gives different wallets different reton ids', function () {
    $a = app(WalletService::class)->open(User::factory()->create(), 'NGN');
    $b = app(WalletService::class)->open(User::factory()->create(), 'NGN');

    expect($a->account_number)->not->toBe($b->account_number);
});

it('exposes the reton id on the wallet API', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    $this->actingAs($user)->getJson("/api/v1/wallets/{$wallet->id}")
        ->assertOk()
        ->assertJsonPath('data.account_number', $wallet->account_number);
});

it('resolves a reton id to its holder via lookup', function () {
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

it('returns 404 for an unknown reton id', function () {
    $me = User::factory()->create();
    app(WalletService::class)->open($me, 'NGN');

    $body = '00000000';
    $unknown = 'R'.$body.RetonId::luhnCheckDigit($body);

    $this->actingAs($me)
        ->getJson('/api/v1/wallets/lookup?account_number='.$unknown)
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

it('rejects a nuban that is not a reton id', function () {
    $me = User::factory()->create();
    app(WalletService::class)->open($me, 'NGN');

    $this->actingAs($me)
        ->getJson('/api/v1/wallets/lookup?account_number=0450041659')
        ->assertStatus(422);
});

it('rejects a malformed reton id', function () {
    $me = User::factory()->create();
    app(WalletService::class)->open($me, 'NGN');

    $this->actingAs($me)
        ->getJson('/api/v1/wallets/lookup?account_number=abc')
        ->assertStatus(422);
});

it('migrates legacy numeric wallet numbers to reton ids', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $wallet->forceFill(['account_number' => '2543297233', 'metadata' => []])->saveQuietly();

    expect(\App\Domain\Wallet\Models\Wallet::reissueLegacyAccountNumbers())->toBeGreaterThan(0);

    $wallet->refresh();

    expect(RetonId::isValid($wallet->account_number))->toBeTrue()
        ->and($wallet->metadata['legacy_account_number'] ?? null)->toBe('2543297233');
});
