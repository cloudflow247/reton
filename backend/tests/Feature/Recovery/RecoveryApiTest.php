<?php

declare(strict_types=1);

use App\Domain\Recovery\Enums\RecoveryStatus;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: User, 2: \App\Domain\Transfers\Models\Transfer}
 */
function recoveryScenario(int $minor = 400_00): array
{
    $sender = User::factory()->create(['transaction_pin' => Hash::make('1234')]);
    $receiver = User::factory()->create(['transaction_pin' => Hash::make('5678')]);
    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(1_000_00, 'NGN'));

    $transfer = app(TransferService::class)->sendNormal($sender, $from->refresh(), $to, Money::of($minor, 'NGN'));

    return [$sender, $receiver, $transfer->fresh()];
}

it('lets the sender report a wrong transfer and freezes the funds', function () {
    [$sender, , $transfer] = recoveryScenario(400_00);

    $this->actingAs($sender)->postJson("/api/v1/transfers/{$transfer->id}/recoveries", [
        'reason' => 'Wrong recipient',
        'pin' => '1234',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'held')
        ->assertJsonPath('data.amount', 40000);

    expect($transfer->receiverWallet->fresh()->held_balance)->toBe(40000);
});

it('rejects a recovery report with the wrong pin', function () {
    [$sender, , $transfer] = recoveryScenario();

    $this->actingAs($sender)->postJson("/api/v1/transfers/{$transfer->id}/recoveries", [
        'reason' => 'oops',
        'pin' => '0000',
    ])->assertStatus(422)->assertJsonPath('code', 'invalid_pin');
});

it('forbids a non-sender from reporting a recovery', function () {
    [, $receiver, $transfer] = recoveryScenario();

    $this->actingAs($receiver)->postJson("/api/v1/transfers/{$transfer->id}/recoveries", [
        'reason' => 'oops',
        'pin' => '5678',
    ])->assertStatus(403);
});

it('lets the receiver return the funds, making the sender whole', function () {
    [$sender, $receiver, $transfer] = recoveryScenario(400_00);

    $recoveryId = $this->actingAs($sender)->postJson("/api/v1/transfers/{$transfer->id}/recoveries", [
        'reason' => 'wrong person',
        'pin' => '1234',
    ])->json('data.id');

    $this->actingAs($receiver)->postJson("/api/v1/recoveries/{$recoveryId}/return", [
        'pin' => '5678',
    ])->assertOk()->assertJsonPath('data.status', 'returned');

    expect($transfer->senderWallet->fresh()->balance)->toBe(100000);
});

it('lets the receiver dispute a recovery, escalating it', function () {
    [$sender, $receiver, $transfer] = recoveryScenario();

    $recoveryId = $this->actingAs($sender)->postJson("/api/v1/transfers/{$transfer->id}/recoveries", [
        'reason' => 'wrong person',
        'pin' => '1234',
    ])->json('data.id');

    $this->actingAs($receiver)->postJson("/api/v1/recoveries/{$recoveryId}/dispute", [
        'reason' => 'It was legitimate',
    ])->assertOk()->assertJsonPath('data.status', 'escalated');
});

it('forbids the sender from returning their own recovery', function () {
    [$sender, , $transfer] = recoveryScenario();

    $recoveryId = $this->actingAs($sender)->postJson("/api/v1/transfers/{$transfer->id}/recoveries", [
        'reason' => 'wrong person',
        'pin' => '1234',
    ])->json('data.id');

    $this->actingAs($sender)->postJson("/api/v1/recoveries/{$recoveryId}/return", [
        'pin' => '1234',
    ])->assertStatus(403);
});

it('lists and shows recoveries a user is party to with their timeline', function () {
    [$sender, $receiver, $transfer] = recoveryScenario();

    $recoveryId = $this->actingAs($sender)->postJson("/api/v1/transfers/{$transfer->id}/recoveries", [
        'reason' => 'wrong person',
        'pin' => '1234',
    ])->json('data.id');

    $this->actingAs($receiver)->getJson('/api/v1/recoveries')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);

    $this->actingAs($sender)->getJson("/api/v1/recoveries/{$recoveryId}")
        ->assertOk()
        ->assertJsonStructure(['data' => ['events' => [['action', 'created_at']]]]);

    expect(Recovery::find($recoveryId)->status)->toBe(RecoveryStatus::Held);
});
