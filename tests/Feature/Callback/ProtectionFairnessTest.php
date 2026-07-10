<?php

declare(strict_types=1);

use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Callback\Services\CallbackService;
use App\Domain\Callback\Services\ProtectionFairnessService;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: User, 2: \App\Domain\Transfers\Models\Transfer}
 */
function fairnessHeld(int $minor = 400_00): array
{
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(5_000_00, 'NGN'));
    $transfer = app(TransferService::class)->sendProtected($sender, $from->refresh(), $to, Money::of($minor, 'NGN'));

    return [$sender, $receiver, $transfer];
}

it('classifies dispute reasons into fair-usage categories', function () {
    $fairness = app(ProtectionFairnessService::class);

    expect($fairness->classifyReason('I sent to the wrong person by accident'))->toBe('wrong_recipient')
        ->and($fairness->classifyReason('This looks like a scam'))->toBe('suspected_fraud')
        ->and($fairness->classifyReason('Item was never delivered'))->toBe('not_delivered');
});

it('stores fairness metadata when a callback is initiated', function () {
    [$sender, , $transfer] = fairnessHeld();

    $callback = app(CallbackService::class)->initiate(
        $transfer,
        $sender,
        'Sent to the wrong person by mistake',
    );

    expect($callback->metadata['fairness']['category'] ?? null)->toBe('wrong_recipient')
        ->and($callback->metadata['fairness']['sender_score'] ?? null)->toBeInt()
        ->and($callback->metadata['fairness']['receiver_score'] ?? null)->toBeInt();
});

it('rejects callbacks with a reason that is too short', function () {
    [$sender, , $transfer] = fairnessHeld();

    app(CallbackService::class)->initiate($transfer, $sender, 'short');
})->throws(\App\Domain\Callback\Exceptions\CannotInitiateCallbackException::class);

it('blocks serial callback abuse under fair-usage limits', function () {
    config(['reton.callback.fairness.max_open_callbacks' => 1]);

    $sender = User::factory()->create();
    $receiverA = User::factory()->create();
    $receiverB = User::factory()->create();
    $from = app(WalletService::class)->open($sender, 'NGN');
    $toA = app(WalletService::class)->open($receiverA, 'NGN');
    $toB = app(WalletService::class)->open($receiverB, 'NGN');
    app(WalletService::class)->fund($from, Money::of(5_000_00, 'NGN'));

    $first = app(TransferService::class)->sendProtected($sender, $from->refresh(), $toA, Money::of(200_00, 'NGN'));
    $second = app(TransferService::class)->sendProtected($sender, $from->refresh(), $toB, Money::of(200_00, 'NGN'));

    app(CallbackService::class)->initiate($first, $sender, 'Wrong recipient on first transfer');

    expect(fn () => app(CallbackService::class)->initiate($second, $sender, 'Wrong recipient on second transfer'))
        ->toThrow(\App\Domain\Callback\Exceptions\CannotInitiateCallbackException::class);
});

it('uses two-sided fairness to release when an abusive high-risk sender expires', function () {
    config(['reton.callback.unanswered_resolution' => 'refund']);

    [$sender, $receiver, $transfer] = fairnessHeld();
    config(['reton.fraud.failed_pin_threshold' => 1, 'reton.fraud.failed_pin_points' => 90]);
    $sender->forceFill(['pin_attempts' => 3])->save();

    $callback = app(CallbackService::class)->initiate(
        $transfer,
        $sender,
        'Please refund this protected payment now',
    );

    $resolved = app(CallbackService::class)->expire($callback->fresh());

    expect($resolved->status)->toBe(CallbackStatus::Released)
        ->and($resolved->metadata['fairness']['resolution'] ?? null)->toBe('release')
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(0)
        ->and($transfer->receiverWallet->fresh()->balance)->toBe(40000);
});

it('lengthens hold windows for large protected amounts', function () {
    config([
        'reton.callback.hold_hours' => 72,
        'reton.callback.fairness.large_amount_minor' => 100_000,
    ]);

    [$sender, $receiver] = fairnessHeld();
    $from = $sender->wallets()->first();
    $to = $receiver->wallets()->first();
    app(WalletService::class)->fund($from->refresh(), Money::of(2_000_00, 'NGN'));

    $transfer = app(TransferService::class)->sendProtected(
        $sender,
        $from->refresh(),
        $to,
        Money::of(150_000, 'NGN'),
    );

    $hours = now()->diffInHours($transfer->hold->expires_at, false);

    expect($hours)->toBeGreaterThanOrEqual(90);
});
