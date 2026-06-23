<?php

declare(strict_types=1);

use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Transfers\Enums\HoldStatus;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Enums\TransferType;
use App\Domain\Transfers\Exceptions\InvalidTransferStateException;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function transfers(): TransferService
{
    return app(TransferService::class);
}

function fundedWallet(int $minor): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    if ($minor > 0) {
        app(WalletService::class)->fund($wallet, Money::of($minor, 'NGN'));
        $wallet->refresh();
    }

    return [$user, $wallet];
}

function escrowBalanceMinor(): int
{
    return app(SystemAccountResolver::class)
        ->resolve(SystemAccount::CallbackHolds, 'NGN')
        ->balanceMinor();
}

it('completes a normal transfer immediately', function () {
    [$sender, $from] = fundedWallet(1_000_00);
    [, $to] = fundedWallet(0);

    $transfer = transfers()->sendNormal($sender, $from, $to, Money::of(300_00, 'NGN'));

    expect($transfer->type)->toBe(TransferType::Normal)
        ->and($transfer->status)->toBe(TransferStatus::Completed)
        ->and($transfer->transaction_id)->not->toBeNull()
        ->and($from->fresh()->balance)->toBe(70000)
        ->and($to->fresh()->balance)->toBe(30000);
});

it('holds funds in escrow for a protected transfer', function () {
    [$sender, $from] = fundedWallet(1_000_00);
    [, $to] = fundedWallet(0);

    $transfer = transfers()->sendProtected($sender, $from, $to, Money::of(400_00, 'NGN'));

    expect($transfer->type)->toBe(TransferType::Protected)
        ->and($transfer->status)->toBe(TransferStatus::Held)
        ->and($from->fresh()->balance)->toBe(60000)   // sender debited
        ->and($to->fresh()->balance)->toBe(0)          // receiver NOT yet credited
        ->and(escrowBalanceMinor())->toBe(40000)       // funds sit in escrow
        ->and($transfer->hold)->not->toBeNull()
        ->and($transfer->hold->status)->toBe(HoldStatus::Active)
        ->and($transfer->hold->expires_at)->not->toBeNull();
});

it('refuses a protected transfer that exceeds the available balance', function () {
    [$sender, $from] = fundedWallet(100_00);
    [, $to] = fundedWallet(0);

    transfers()->sendProtected($sender, $from, $to, Money::of(500_00, 'NGN'));
})->throws(InsufficientFundsException::class);

it('releases a protected transfer to the receiver', function () {
    [$sender, $from] = fundedWallet(1_000_00);
    [, $to] = fundedWallet(0);

    $transfer = transfers()->sendProtected($sender, $from, $to, Money::of(400_00, 'NGN'));
    $released = transfers()->release($transfer);

    expect($released->status)->toBe(TransferStatus::Completed)
        ->and($released->hold->fresh()->status)->toBe(HoldStatus::Released)
        ->and($to->fresh()->balance)->toBe(40000)
        ->and(escrowBalanceMinor())->toBe(0);
});

it('refunds a protected transfer back to the sender', function () {
    [$sender, $from] = fundedWallet(1_000_00);
    [, $to] = fundedWallet(0);

    $transfer = transfers()->sendProtected($sender, $from, $to, Money::of(400_00, 'NGN'));
    $refunded = transfers()->refund($transfer, 'callback upheld');

    expect($refunded->status)->toBe(TransferStatus::Refunded)
        ->and($refunded->hold->fresh()->status)->toBe(HoldStatus::Refunded)
        ->and($from->fresh()->balance)->toBe(100000)   // sender made whole
        ->and($to->fresh()->balance)->toBe(0)
        ->and(escrowBalanceMinor())->toBe(0);
});

it('cannot release a normal transfer', function () {
    [$sender, $from] = fundedWallet(1_000_00);
    [, $to] = fundedWallet(0);

    $transfer = transfers()->sendNormal($sender, $from, $to, Money::of(100_00, 'NGN'));
    transfers()->release($transfer);
})->throws(InvalidTransferStateException::class);

it('cannot release the same protected transfer twice', function () {
    [$sender, $from] = fundedWallet(1_000_00);
    [, $to] = fundedWallet(0);

    $transfer = transfers()->sendProtected($sender, $from, $to, Money::of(400_00, 'NGN'));
    transfers()->release($transfer);
    transfers()->release($transfer->fresh());
})->throws(InvalidTransferStateException::class);

it('conserves money across a protected hold and release', function () {
    [$sender, $from] = fundedWallet(1_000_00);
    [, $to] = fundedWallet(0);

    $transfer = transfers()->sendProtected($sender, $from, $to, Money::of(250_00, 'NGN'));
    transfers()->release($transfer);

    $total = Wallet::sum('balance') + escrowBalanceMinor();

    expect($total)->toBe(100000); // the originally funded 1,000.00 NGN, intact
});
