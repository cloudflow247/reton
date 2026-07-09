<?php

declare(strict_types=1);

use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Callback\Services\CallbackService;
use App\Domain\Marketplace\Enums\DigitalOrderStatus;
use App\Domain\Marketplace\Services\DigitalMarketplaceService;
use App\Domain\Transfers\Enums\HoldStatus;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: User, 2: Transfer}
 */
function protectedHeld(int $minor = 400_00): array
{
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(1_000_00, 'NGN'));

    $transfer = app(TransferService::class)->sendProtected($sender, $from->refresh(), $to, Money::of($minor, 'NGN'));

    return [$sender, $receiver, $transfer];
}

it('expires overdue pending callbacks and resolves them', function () {
    [$sender, , $transfer] = protectedHeld(400_00);
    $callback = app(CallbackService::class)->initiate($transfer, $sender, 'no response');
    $callback->forceFill(['responds_by' => now()->subHour()])->save();

    $this->artisan('callbacks:expire')->assertSuccessful();

    expect($callback->fresh()->status)->toBe(CallbackStatus::Refunded)
        ->and($transfer->senderWallet->fresh()->balance)->toBe(100000);
});

it('leaves callbacks within their response window untouched', function () {
    [$sender, , $transfer] = protectedHeld();
    $callback = app(CallbackService::class)->initiate($transfer, $sender, 'still waiting');

    $this->artisan('callbacks:expire')->assertSuccessful();

    expect($callback->fresh()->status)->toBe(CallbackStatus::Pending);
});

it('auto-releases protected transfers whose hold has expired', function () {
    [, , $transfer] = protectedHeld(400_00);
    $transfer->hold->forceFill(['expires_at' => now()->subHour()])->save();

    $this->artisan('transfers:auto-release')->assertSuccessful();

    expect($transfer->fresh()->status)->toBe(TransferStatus::Completed)
        ->and($transfer->hold->fresh()->status)->toBe(HoldStatus::Released)
        ->and($transfer->receiverWallet->fresh()->balance)->toBe(40000)
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(0);
});

it('does not auto-release a transfer that still has an open callback', function () {
    [$sender, , $transfer] = protectedHeld(400_00);
    $transfer->hold->forceFill(['expires_at' => now()->subHour()])->save();
    app(CallbackService::class)->initiate($transfer, $sender, 'disputed');

    $this->artisan('transfers:auto-release')->assertSuccessful();

    expect($transfer->fresh()->status)->toBe(TransferStatus::Held)
        ->and($transfer->receiverWallet->fresh()->balance)->toBe(40000)
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(40000);
});

it('does not auto-release a hold that has not yet expired', function () {
    [, , $transfer] = protectedHeld(400_00);

    $this->artisan('transfers:auto-release')->assertSuccessful();

    expect($transfer->fresh()->status)->toBe(TransferStatus::Held)
        ->and($transfer->receiverWallet->fresh()->balance)->toBe(40000)
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(40000);
});

it('auto-refunds digital orders past the seller delivery deadline', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    app(WalletService::class)->open($seller, 'NGN');
    $buyerWallet = app(WalletService::class)->open($buyer, 'NGN');
    app(WalletService::class)->fund($buyerWallet, Money::of(50_000_00, 'NGN'));

    $listing = app(DigitalMarketplaceService::class)->createListing(
        $seller,
        'Preset pack',
        'Lightroom presets.',
        Money::of(10_000_00, 'NGN'),
        'DOWNLOAD-LINK',
    );

    $order = app(DigitalMarketplaceService::class)->purchase($buyer, $listing, $buyerWallet->refresh());

    $this->travel(73)->hours();

    $this->artisan('marketplace:expire-undelivered')->assertSuccessful();

    expect($order->fresh()->status)->toBe(DigitalOrderStatus::Refunded)
        ->and($order->transfer?->fresh()->status)->toBe(TransferStatus::Refunded)
        ->and($buyerWallet->fresh()->balance)->toBe(50_000_00);
});
