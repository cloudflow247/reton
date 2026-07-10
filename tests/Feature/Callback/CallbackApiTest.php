<?php

declare(strict_types=1);

use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: User, 2: Transfer}
 */
function protectedScenario(int $minor = 400_00): array
{
    $sender = User::factory()->create(['transaction_pin' => Hash::make('1234')]);
    $receiver = User::factory()->create(['transaction_pin' => Hash::make('5678')]);

    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(1_000_00, 'NGN'));

    $transfer = app(TransferService::class)->sendProtected($sender, $from->refresh(), $to, Money::of($minor, 'NGN'));

    return [$sender, $receiver, $transfer];
}

it('lets the sender initiate a callback on a held transfer', function () {
    [$sender, , $transfer] = protectedScenario();

    $this->actingAs($sender)->postJson("/api/v1/transfers/{$transfer->id}/callbacks", [
        'reason' => 'Goods never arrived',
        'pin' => '1234',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.reason', 'Goods never arrived');
});

it('rejects callback initiation with the wrong pin', function () {
    [$sender, , $transfer] = protectedScenario();

    $this->actingAs($sender)->postJson("/api/v1/transfers/{$transfer->id}/callbacks", [
        'reason' => 'Payment dispute please review',
        'pin' => '0000',
    ])->assertStatus(422)->assertJsonPath('code', 'invalid_pin');
});

it('forbids a non-sender from initiating a callback', function () {
    [, $receiver, $transfer] = protectedScenario();

    $this->actingAs($receiver)->postJson("/api/v1/transfers/{$transfer->id}/callbacks", [
        'reason' => 'Payment dispute please review',
        'pin' => '5678',
    ])->assertStatus(403);
});

it('lets the receiver accept a callback, refunding the sender', function () {
    [$sender, $receiver, $transfer] = protectedScenario(400_00);

    $callbackId = $this->actingAs($sender)->postJson("/api/v1/transfers/{$transfer->id}/callbacks", [
        'reason' => 'Payment dispute please review',
        'pin' => '1234',
    ])->json('data.id');

    $this->actingAs($receiver)->postJson("/api/v1/callbacks/{$callbackId}/accept", [
        'pin' => '5678',
    ])->assertOk()->assertJsonPath('data.status', 'refunded');

    expect($transfer->senderWallet->fresh()->balance)->toBe(100000);
});

it('lets the receiver reject a callback, escalating it', function () {
    [$sender, $receiver, $transfer] = protectedScenario();

    $callbackId = $this->actingAs($sender)->postJson("/api/v1/transfers/{$transfer->id}/callbacks", [
        'reason' => 'Payment dispute please review',
        'pin' => '1234',
    ])->json('data.id');

    $this->actingAs($receiver)->postJson("/api/v1/callbacks/{$callbackId}/reject", [
        'reason' => 'I delivered it',
    ])->assertOk()->assertJsonPath('data.status', 'escalated');
});

it('forbids the sender from responding to their own callback', function () {
    [$sender, , $transfer] = protectedScenario();

    $callbackId = $this->actingAs($sender)->postJson("/api/v1/transfers/{$transfer->id}/callbacks", [
        'reason' => 'Payment dispute please review',
        'pin' => '1234',
    ])->json('data.id');

    $this->actingAs($sender)->postJson("/api/v1/callbacks/{$callbackId}/accept", [
        'pin' => '1234',
    ])->assertStatus(403);
});

it('lets either party add evidence and view the timeline', function () {
    [$sender, $receiver, $transfer] = protectedScenario();

    $callbackId = $this->actingAs($sender)->postJson("/api/v1/transfers/{$transfer->id}/callbacks", [
        'reason' => 'Payment dispute please review',
        'pin' => '1234',
    ])->json('data.id');

    $this->actingAs($receiver)->postJson("/api/v1/callbacks/{$callbackId}/evidence", [
        'note' => 'Delivery photo attached',
        'url' => 'https://example.com/proof.jpg',
    ])->assertOk();

    $this->actingAs($sender)->getJson("/api/v1/callbacks/{$callbackId}")
        ->assertOk()
        ->assertJsonPath('data.id', $callbackId)
        ->assertJsonStructure(['data' => ['events' => [['action', 'created_at']]]]);
});

it('lists the callbacks a user is party to', function () {
    [$sender, $receiver, $transfer] = protectedScenario();

    $this->actingAs($sender)->postJson("/api/v1/transfers/{$transfer->id}/callbacks", [
        'reason' => 'Payment dispute please review',
        'pin' => '1234',
    ])->assertCreated();

    $this->actingAs($receiver)->getJson('/api/v1/callbacks')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);
});
