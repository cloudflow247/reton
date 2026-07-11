<?php

declare(strict_types=1);

use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Recovery\Enums\RecoveryAction;
use App\Domain\Recovery\Enums\RecoveryResolution;
use App\Domain\Recovery\Enums\RecoveryStatus;
use App\Domain\Recovery\Exceptions\CannotReportRecoveryException;
use App\Domain\Recovery\Exceptions\RecoveryAlreadyOpenException;
use App\Domain\Recovery\Services\RecoveryService;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function recoveries(): RecoveryService
{
    return app(RecoveryService::class);
}

/**
 * A completed normal transfer of $minor from a funded sender to a fresh receiver.
 *
 * @return array{0: User, 1: User, 2: Transfer}
 */
function wrongTransfer(int $minor = 400_00): array
{
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(1_000_00, 'NGN'));

    $transfer = app(TransferService::class)->sendNormal($sender, $from->refresh(), $to, Money::of($minor, 'NGN'));

    return [$sender, $receiver, $transfer->fresh()];
}

function feesRevenueMinor(): int
{
    return app(SystemAccountResolver::class)
        ->resolve(SystemAccount::FeesRevenue, 'NGN')
        ->balanceMinor();
}

it('freezes the receiver funds when an eligible recovery is reported', function () {
    [$sender, , $transfer] = wrongTransfer(400_00);

    $recovery = recoveries()->report($transfer, $sender, 'Sent to the wrong person');

    expect($recovery->status)->toBe(RecoveryStatus::Held)
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(40000)
        ->and($transfer->receiverWallet->fresh()->availableMinor())->toBe(0)
        ->and($transfer->receiverWallet->fresh()->balance)->toBe(40000) // funds still there, just frozen
        ->and($recovery->events()->where('action', RecoveryAction::HeldPlaced)->exists())->toBeTrue();
});

it('declines a recovery reported after the eligibility window', function () {
    [$sender, , $transfer] = wrongTransfer(400_00);
    $transfer->forceFill(['completed_at' => now()->subDays(30)])->save();

    $recovery = recoveries()->report($transfer->fresh(), $sender, 'too late');

    expect($recovery->status)->toBe(RecoveryStatus::Declined)
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(0);
});

it('declines a recovery when the receiver has already spent the funds', function () {
    [$sender, , $transfer] = wrongTransfer(400_00);
    // Receiver spends part of the funds, leaving less than the recovery amount.
    app(WalletService::class)->withdraw($transfer->receiverWallet, Money::of(300_00, 'NGN'));

    $recovery = recoveries()->report($transfer->fresh(), $sender, 'spent already');

    expect($recovery->status)->toBe(RecoveryStatus::Declined)
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(0);
});

it('cannot report a recovery on a protected transfer', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(1_000_00, 'NGN'));
    $protected = app(TransferService::class)->sendProtected($sender, $from->refresh(), $to, Money::of(100_00, 'NGN'));

    recoveries()->report($protected, $sender, 'nope');
})->throws(CannotReportRecoveryException::class);

it('cannot open a second recovery while one is open', function () {
    [$sender, , $transfer] = wrongTransfer();

    recoveries()->report($transfer, $sender, 'first');
    recoveries()->report($transfer->fresh(), $sender, 'second');
})->throws(RecoveryAlreadyOpenException::class);

it('claws funds back to the sender when the receiver returns them', function () {
    [$sender, , $transfer] = wrongTransfer(400_00);
    $recovery = recoveries()->report($transfer, $sender, 'wrong person');

    $returned = recoveries()->returnToSender($recovery);

    expect($returned->status)->toBe(RecoveryStatus::Returned)
        ->and($transfer->senderWallet->fresh()->balance)->toBe(100000) // made whole
        ->and($transfer->receiverWallet->fresh()->balance)->toBe(0)
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(0);
});

it('charges a recovery fee on a successful return', function () {
    config(['reton.recovery.fee_bps' => 500]); // 5%
    [$sender, , $transfer] = wrongTransfer(400_00);
    $recovery = recoveries()->report($transfer, $sender, 'wrong person');

    $returned = recoveries()->returnToSender($recovery);

    // 5% of 400.00 = 20.00 fee; sender receives 380.00 of the 400.00 clawed back.
    expect($returned->fee)->toBe(2000)
        ->and($transfer->senderWallet->fresh()->balance)->toBe(98000)
        ->and(feesRevenueMinor())->toBe(2000);
});

it('unfreezes the funds when a recovery is released to the receiver', function () {
    [$sender, , $transfer] = wrongTransfer(400_00);
    $recovery = recoveries()->report($transfer, $sender, 'changed my mind');

    $released = recoveries()->releaseToReceiver($recovery);

    expect($released->status)->toBe(RecoveryStatus::Declined)
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(0)
        ->and($transfer->receiverWallet->fresh()->balance)->toBe(40000)
        ->and($transfer->receiverWallet->fresh()->availableMinor())->toBe(40000);
});

it('escalates a disputed recovery while keeping funds frozen', function () {
    [$sender, $receiver, $transfer] = wrongTransfer(400_00);
    $recovery = recoveries()->report($transfer, $sender, 'wrong person');

    $disputed = recoveries()->dispute($recovery, $receiver, 'It was a legitimate payment');

    expect($disputed->status)->toBe(RecoveryStatus::Escalated)
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(40000);
});

it('lets an admin resolve an escalated recovery by returning the funds', function () {
    [$sender, $receiver, $transfer] = wrongTransfer(400_00);
    $admin = User::factory()->create();
    $recovery = recoveries()->report($transfer, $sender, 'wrong person');
    recoveries()->dispute($recovery->fresh(), $receiver, 'mine');

    $resolved = recoveries()->resolve($recovery->fresh(), RecoveryResolution::Return, $admin);

    expect($resolved->status)->toBe(RecoveryStatus::Returned)
        ->and($transfer->senderWallet->fresh()->balance)->toBe(100000);
});

it('escalates an unanswered recovery on expiry', function () {
    [$sender, , $transfer] = wrongTransfer(400_00);
    $recovery = recoveries()->report($transfer, $sender, 'wrong person');
    $recovery->forceFill(['expires_at' => now()->subHour()])->save();

    $escalated = recoveries()->expire($recovery->fresh());

    expect($escalated->status)->toBe(RecoveryStatus::Escalated)
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(40000);
});

it('is idempotent when returning an already-resolved recovery', function () {
    [$sender, , $transfer] = wrongTransfer(400_00);
    $recovery = recoveries()->report($transfer, $sender, 'wrong person');
    $first = recoveries()->returnToSender($recovery);

    $second = recoveries()->returnToSender($recovery->fresh());

    expect($second->id)->toBe($first->id)
        ->and($second->status)->toBe(RecoveryStatus::Returned)
        ->and($transfer->senderWallet->fresh()->balance)->toBe(100000)
        ->and($transfer->receiverWallet->fresh()->balance)->toBe(0)
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(0);
});
