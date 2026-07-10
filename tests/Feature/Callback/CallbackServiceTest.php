<?php

declare(strict_types=1);

use App\Domain\Callback\Enums\CallbackAction;
use App\Domain\Callback\Enums\CallbackResolution;
use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Callback\Exceptions\CallbackAlreadyOpenException;
use App\Domain\Callback\Exceptions\CallbackNotOpenException;
use App\Domain\Callback\Exceptions\CannotInitiateCallbackException;
use App\Domain\Callback\Services\CallbackService;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function callbacks(): CallbackService
{
    return app(CallbackService::class);
}

/**
 * @return array{0: User, 1: User, 2: Transfer}
 */
function heldProtectedTransfer(int $minor = 400_00): array
{
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(1_000_00, 'NGN'));

    $transfer = app(TransferService::class)->sendProtected(
        $sender,
        $from->refresh(),
        $to,
        Money::of($minor, 'NGN'),
    );

    return [$sender, $receiver, $transfer];
}

it('lets a sender initiate a callback on a held protected transfer', function () {
    [$sender, , $transfer] = heldProtectedTransfer();

    $callback = callbacks()->initiate($transfer, $sender, 'Goods never arrived');

    expect($callback->status)->toBe(CallbackStatus::Pending)
        ->and($callback->reason)->toBe('Goods never arrived')
        ->and($callback->responds_by)->not->toBeNull()
        ->and($transfer->fresh()->status)->toBe(TransferStatus::Held)
        ->and($callback->events()->where('action', CallbackAction::Initiated)->exists())->toBeTrue();
});

it('cannot initiate a callback on a normal transfer', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(1_000_00, 'NGN'));

    $normal = app(TransferService::class)->sendNormal($sender, $from->refresh(), $to, Money::of(100_00, 'NGN'));

    callbacks()->initiate($normal, $sender, 'Accidental unprotected send');
})->throws(CannotInitiateCallbackException::class);

it('cannot open a second callback while one is already open', function () {
    [$sender, , $transfer] = heldProtectedTransfer();

    callbacks()->initiate($transfer, $sender, 'First callback on this transfer');
    callbacks()->initiate($transfer->fresh(), $sender, 'Second callback should fail');
})->throws(CallbackAlreadyOpenException::class);

it('refunds the sender when the receiver accepts', function () {
    [$sender, $receiver, $transfer] = heldProtectedTransfer(400_00);

    $callback = callbacks()->initiate($transfer, $sender, 'wrong recipient');
    $resolved = callbacks()->accept($callback, $receiver);

    expect($resolved->status)->toBe(CallbackStatus::Refunded)
        ->and($transfer->fresh()->status)->toBe(TransferStatus::Refunded)
        ->and($transfer->senderWallet->fresh()->balance)->toBe(100000); // made whole
});

it('escalates the callback when the receiver rejects', function () {
    [$sender, $receiver, $transfer] = heldProtectedTransfer();

    $callback = callbacks()->initiate($transfer, $sender, 'Payment dispute please review');
    $rejected = callbacks()->reject($callback, $receiver, 'I delivered the goods');

    expect($rejected->status)->toBe(CallbackStatus::Escalated)
        ->and($transfer->fresh()->status)->toBe(TransferStatus::Held) // funds stay escrowed
        ->and($rejected->events()->where('action', CallbackAction::Rejected)->exists())->toBeTrue();
});

it('lets an admin resolve an escalated callback by releasing to the receiver', function () {
    [$sender, $receiver, $transfer] = heldProtectedTransfer(400_00);
    $admin = User::factory()->create();

    $callback = callbacks()->initiate($transfer, $sender, 'Payment dispute please review');
    callbacks()->reject($callback->fresh(), $receiver, 'delivered');
    $resolved = callbacks()->resolve($callback->fresh(), CallbackResolution::Release, $admin);

    expect($resolved->status)->toBe(CallbackStatus::Released)
        ->and($transfer->receiverWallet->fresh()->balance)->toBe(40000)
        ->and($transfer->fresh()->status)->toBe(TransferStatus::Completed);
});

it('lets an admin resolve an escalated callback by refunding the sender', function () {
    [$sender, $receiver, $transfer] = heldProtectedTransfer(400_00);
    $admin = User::factory()->create();

    $callback = callbacks()->initiate($transfer, $sender, 'Payment dispute please review');
    callbacks()->reject($callback->fresh(), $receiver, 'delivered');
    $resolved = callbacks()->resolve($callback->fresh(), CallbackResolution::Refund, $admin);

    expect($resolved->status)->toBe(CallbackStatus::Refunded)
        ->and($transfer->senderWallet->fresh()->balance)->toBe(100000);
});

it('records evidence on the callback timeline', function () {
    [$sender, $receiver, $transfer] = heldProtectedTransfer();
    $callback = callbacks()->initiate($transfer, $sender, 'Payment dispute please review');

    callbacks()->addEvidence($callback, $receiver, 'Tracking shows delivered', ['url' => 'https://x/y']);

    expect($callback->events()->where('action', CallbackAction::EvidenceAdded)->exists())->toBeTrue();
});

it('auto-resolves an unanswered callback on expiry (refund by default)', function () {
    [$sender, , $transfer] = heldProtectedTransfer(400_00);
    $callback = callbacks()->initiate($transfer, $sender, 'no response expected');

    $callback->forceFill(['responds_by' => now()->subHour()])->save();

    $resolved = callbacks()->expire($callback->fresh());

    expect($resolved->status)->toBe(CallbackStatus::Refunded)
        ->and($transfer->senderWallet->fresh()->balance)->toBe(100000)
        ->and($resolved->events()->where('action', CallbackAction::Expired)->exists())->toBeTrue();
});

it('cannot accept a callback that is already resolved', function () {
    [$sender, $receiver, $transfer] = heldProtectedTransfer();
    $callback = callbacks()->initiate($transfer, $sender, 'Payment dispute please review');
    callbacks()->accept($callback, $receiver);

    callbacks()->accept($callback->fresh(), $receiver);
})->throws(CallbackNotOpenException::class);
