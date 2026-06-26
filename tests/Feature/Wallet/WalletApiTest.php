<?php

declare(strict_types=1);

use App\Domain\Ledger\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Create a user (with a PIN) and their funded NGN wallet.
 */
function userWithWallet(int $fundMinor = 0, string $pin = '1234'): array
{
    $user = User::factory()->create(['transaction_pin' => Hash::make($pin)]);
    $wallet = app(WalletService::class)->open($user, 'NGN');

    if ($fundMinor > 0) {
        app(WalletService::class)->fund($wallet, Money::of($fundMinor, 'NGN'));
        $wallet->refresh();
    }

    return [$user, $wallet];
}

it('lists the authenticated user\'s wallets', function () {
    [$user, $wallet] = userWithWallet();

    $this->actingAs($user)->getJson('/api/v1/wallets')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.currency', 'NGN')
        ->assertJsonPath('data.0.id', $wallet->id);
});

it('shows a wallet the user owns', function () {
    [$user, $wallet] = userWithWallet(500_00);

    $this->actingAs($user)->getJson("/api/v1/wallets/{$wallet->id}")
        ->assertOk()
        ->assertJsonPath('data.balance', 50000)
        ->assertJsonPath('data.available_balance', 50000);
});

it('forbids viewing another user\'s wallet', function () {
    [, $wallet] = userWithWallet();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->getJson("/api/v1/wallets/{$wallet->id}")
        ->assertStatus(403)
        ->assertJsonPath('code', 'forbidden');
});

it('transfers funds between wallets with a valid pin', function () {
    [$sender, $senderWallet] = userWithWallet(1_000_00);
    [, $receiverWallet] = userWithWallet();

    $this->actingAs($sender)->postJson("/api/v1/wallets/{$senderWallet->id}/transfer", [
        'to_wallet_id' => $receiverWallet->id,
        'amount' => 300_00,
        'pin' => '1234',
    ])->assertOk()->assertJsonPath('success', true);

    expect($senderWallet->fresh()->balance)->toBe(70000)
        ->and($receiverWallet->fresh()->balance)->toBe(30000);
});

it('rejects a transfer with the wrong pin', function () {
    [$sender, $senderWallet] = userWithWallet(1_000_00);
    [, $receiverWallet] = userWithWallet();

    $this->actingAs($sender)->postJson("/api/v1/wallets/{$senderWallet->id}/transfer", [
        'to_wallet_id' => $receiverWallet->id,
        'amount' => 300_00,
        'pin' => '9999',
    ])->assertStatus(422)->assertJsonPath('code', 'invalid_pin');

    expect($senderWallet->fresh()->balance)->toBe(100000);
});

it('rejects a transfer that exceeds the balance', function () {
    [$sender, $senderWallet] = userWithWallet(100_00);
    [, $receiverWallet] = userWithWallet();

    $this->actingAs($sender)->postJson("/api/v1/wallets/{$senderWallet->id}/transfer", [
        'to_wallet_id' => $receiverWallet->id,
        'amount' => 500_00,
        'pin' => '1234',
    ])->assertStatus(422)->assertJsonPath('code', 'insufficient_funds');
});

it('forbids transferring from a wallet the user does not own', function () {
    [, $senderWallet] = userWithWallet(1_000_00);
    [$intruder, $intruderWallet] = userWithWallet();

    $this->actingAs($intruder)->postJson("/api/v1/wallets/{$senderWallet->id}/transfer", [
        'to_wallet_id' => $intruderWallet->id,
        'amount' => 100_00,
        'pin' => '1234',
    ])->assertStatus(403);
});

it('treats a repeated Idempotency-Key as a single transfer', function () {
    [$sender, $senderWallet] = userWithWallet(1_000_00);
    [, $receiverWallet] = userWithWallet();

    $payload = [
        'to_wallet_id' => $receiverWallet->id,
        'amount' => 200_00,
        'pin' => '1234',
    ];

    $this->actingAs($sender)
        ->withHeaders(['Idempotency-Key' => 'transfer-xyz'])
        ->postJson("/api/v1/wallets/{$senderWallet->id}/transfer", $payload)
        ->assertOk();

    $this->actingAs($sender)
        ->withHeaders(['Idempotency-Key' => 'transfer-xyz'])
        ->postJson("/api/v1/wallets/{$senderWallet->id}/transfer", $payload)
        ->assertOk();

    expect($senderWallet->fresh()->balance)->toBe(80000)
        ->and(Transaction::where('type', 'internal_transfer')->count())->toBe(1);
});

it('withdraws funds with a valid pin', function () {
    [$user, $wallet] = userWithWallet(500_00);

    $this->actingAs($user)->postJson("/api/v1/wallets/{$wallet->id}/withdraw", [
        'amount' => 200_00,
        'pin' => '1234',
    ])->assertOk();

    expect($wallet->fresh()->balance)->toBe(30000);
});

it('returns a paginated statement of wallet transactions', function () {
    [$user, $wallet] = userWithWallet(1_000_00);
    [, $receiverWallet] = userWithWallet();

    app(WalletService::class)->transfer($wallet, $receiverWallet, Money::of(100_00, 'NGN'));

    $response = $this->actingAs($user)->getJson("/api/v1/wallets/{$wallet->id}/transactions")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [['id', 'direction', 'amount', 'transaction' => ['reference', 'type']]],
            'meta' => ['pagination' => ['total', 'per_page', 'current_page', 'last_page']],
        ]);

    // Funding (credit) + transfer-out (debit) = two statement lines.
    expect($response->json('meta.pagination.total'))->toBe(2);
});
