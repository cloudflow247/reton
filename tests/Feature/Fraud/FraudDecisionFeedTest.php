<?php

declare(strict_types=1);

use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Callback\Services\CallbackService;
use App\Domain\Recovery\Enums\RecoveryStatus;
use App\Domain\Recovery\Services\RecoveryService;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Force the next fraud assessment to come back high-risk by leaning on the
 * failed-PIN signal for the given user.
 */
function makeHighRisk(User $user): void
{
    config(['reton.fraud.failed_pin_threshold' => 1, 'reton.fraud.failed_pin_points' => 90]);
    $user->forceFill(['pin_attempts' => 3])->save();
}

/**
 * @return array{0: User, 1: User, 2: Transfer}
 */
function heldProtected(int $minor = 400_00): array
{
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(1_000_00, 'NGN'));
    $transfer = app(TransferService::class)->sendProtected($sender, $from->refresh(), $to, Money::of($minor, 'NGN'));

    return [$sender, $receiver, $transfer];
}

/**
 * @return array{0: User, 1: User, 2: Transfer}
 */
function completedNormal(int $minor = 400_00): array
{
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(1_000_00, 'NGN'));
    $transfer = app(TransferService::class)->sendNormal($sender, $from->refresh(), $to, Money::of($minor, 'NGN'));

    return [$sender, $receiver, $transfer->fresh()];
}

it('releases an expired callback to a low-risk receiver when configured to', function () {
    config(['reton.callback.unanswered_resolution' => 'release']);
    [$sender, , $transfer] = heldProtected(400_00);

    $callback = app(CallbackService::class)->initiate($transfer, $sender, 'no response');
    $resolved = app(CallbackService::class)->expire($callback->fresh());

    expect($resolved->status)->toBe(CallbackStatus::Released)
        ->and($transfer->receiverWallet->fresh()->balance)->toBe(40000);
});

it('overrides to refund on expiry when the receiver is high-risk', function () {
    config(['reton.callback.unanswered_resolution' => 'release']);
    [$sender, $receiver, $transfer] = heldProtected(400_00);
    makeHighRisk($receiver);

    $callback = app(CallbackService::class)->initiate($transfer, $sender, 'suspicious receiver');
    $resolved = app(CallbackService::class)->expire($callback->fresh());

    // Despite the 'release' default, the high-risk receiver is not paid out.
    expect($resolved->status)->toBe(CallbackStatus::Refunded)
        ->and($transfer->senderWallet->fresh()->balance)->toBe(100000)
        ->and($transfer->receiverWallet->fresh()->balance)->toBe(0);
});

it('declines an out-of-window recovery for a low-risk receiver', function () {
    [$sender, , $transfer] = completedNormal(400_00);
    $transfer->forceFill(['completed_at' => now()->subDays(30)])->save();

    $recovery = app(RecoveryService::class)->report($transfer->fresh(), $sender, 'too late');

    expect($recovery->status)->toBe(RecoveryStatus::Declined);
});

it('extends the recovery window for a high-risk receiver', function () {
    [$sender, $receiver, $transfer] = completedNormal(400_00);
    $transfer->forceFill(['completed_at' => now()->subDays(30)])->save();
    makeHighRisk($receiver);

    $recovery = app(RecoveryService::class)->report($transfer->fresh(), $sender, 'flagged receiver');

    expect($recovery->status)->toBe(RecoveryStatus::Held)
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(40000);
});
