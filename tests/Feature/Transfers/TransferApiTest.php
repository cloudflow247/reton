<?php

declare(strict_types=1);

use App\Domain\Transfers\Models\Transfer;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function partyWithWallet(int $fundMinor = 0, string $pin = '1234'): array
{
    $user = User::factory()->create(['transaction_pin' => Hash::make($pin)]);
    $wallet = app(WalletService::class)->open($user, 'NGN');

    if ($fundMinor > 0) {
        app(WalletService::class)->fund($wallet, Money::of($fundMinor, 'NGN'));
        $wallet->refresh();
    }

    return [$user, $wallet];
}

it('creates a normal transfer that settles immediately', function () {
    [$sender, $from] = partyWithWallet(1_000_00);
    [, $to] = partyWithWallet();

    $this->actingAs($sender)->postJson('/api/v1/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 250_00,
        'type' => 'normal',
        'pin' => '1234',
    ])->assertCreated()
        ->assertJsonPath('data.type', 'normal')
        ->assertJsonPath('data.status', 'completed');

    expect($from->fresh()->balance)->toBe(75000)
        ->and($to->fresh()->balance)->toBe(25000);
});

it('creates a protected transfer with receiver pending balance', function () {
    [$sender, $from] = partyWithWallet(1_000_00);
    [, $to] = partyWithWallet();

    $this->actingAs($sender)->postJson('/api/v1/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 400_00,
        'type' => 'protected',
        'pin' => '1234',
    ])->assertCreated()
        ->assertJsonPath('data.type', 'protected')
        ->assertJsonPath('data.status', 'held');

    expect($from->fresh()->balance)->toBe(60000)
        ->and($to->fresh()->balance)->toBe(40000)
        ->and($to->fresh()->held_balance)->toBe(40000)
        ->and($to->fresh()->availableMinor())->toBe(0);
});

it('rejects a transfer with the wrong pin', function () {
    [$sender, $from] = partyWithWallet(1_000_00);
    [, $to] = partyWithWallet();

    $this->actingAs($sender)->postJson('/api/v1/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 100_00,
        'type' => 'normal',
        'pin' => '0000',
    ])->assertStatus(422)->assertJsonPath('code', 'invalid_pin');

    expect($from->fresh()->balance)->toBe(100000);
});

it('forbids transferring from a wallet the user does not own', function () {
    [, $from] = partyWithWallet(1_000_00);
    [$intruder, $intruderWallet] = partyWithWallet();

    $this->actingAs($intruder)->postJson('/api/v1/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $intruderWallet->id,
        'amount' => 100_00,
        'type' => 'normal',
        'pin' => '1234',
    ])->assertStatus(403);
});

it('lets the sender release a protected transfer', function () {
    [$sender, $from] = partyWithWallet(1_000_00);
    [, $to] = partyWithWallet();

    $transferId = $this->actingAs($sender)->postJson('/api/v1/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 400_00,
        'type' => 'protected',
        'pin' => '1234',
    ])->json('data.id');

    $this->actingAs($sender)->postJson("/api/v1/transfers/{$transferId}/release", [
        'pin' => '1234',
    ])->assertOk()->assertJsonPath('data.status', 'completed');

    expect($to->fresh()->balance)->toBe(40000);
});

it('forbids a non-sender from releasing a protected transfer', function () {
    [$sender, $from] = partyWithWallet(1_000_00);
    [$receiver, $to] = partyWithWallet();

    $transferId = $this->actingAs($sender)->postJson('/api/v1/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 400_00,
        'type' => 'protected',
        'pin' => '1234',
    ])->json('data.id');

    $this->actingAs($receiver)->postJson("/api/v1/transfers/{$transferId}/release", [
        'pin' => '1234',
    ])->assertStatus(403);
});

it('lists and shows the user\'s transfers', function () {
    [$sender, $from] = partyWithWallet(1_000_00);
    [, $to] = partyWithWallet();

    $transferId = $this->actingAs($sender)->postJson('/api/v1/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 100_00,
        'type' => 'normal',
        'pin' => '1234',
    ])->json('data.id');

    $this->actingAs($sender)->getJson('/api/v1/transfers')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);

    $this->actingAs($sender)->getJson("/api/v1/transfers/{$transferId}")
        ->assertOk()
        ->assertJsonPath('data.id', $transferId);

    expect(Transfer::count())->toBe(1);
});
