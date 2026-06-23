<?php

declare(strict_types=1);

use App\Domain\Recovery\Enums\RecoveryStatus;
use App\Domain\Recovery\Services\RecoveryService;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('escalates held recoveries whose response window has elapsed', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(1_000_00, 'NGN'));
    $transfer = app(TransferService::class)->sendNormal($sender, $from->refresh(), $to, Money::of(400_00, 'NGN'));

    $recovery = app(RecoveryService::class)->report($transfer->fresh(), $sender, 'wrong person');
    $recovery->forceFill(['expires_at' => now()->subHour()])->save();

    $this->artisan('recoveries:escalate')->assertSuccessful();

    expect($recovery->fresh()->status)->toBe(RecoveryStatus::Escalated)
        ->and($transfer->receiverWallet->fresh()->held_balance)->toBe(40000);
});

it('leaves in-window held recoveries untouched', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(1_000_00, 'NGN'));
    $transfer = app(TransferService::class)->sendNormal($sender, $from->refresh(), $to, Money::of(400_00, 'NGN'));

    $recovery = app(RecoveryService::class)->report($transfer->fresh(), $sender, 'wrong person');

    $this->artisan('recoveries:escalate')->assertSuccessful();

    expect($recovery->fresh()->status)->toBe(RecoveryStatus::Held);
});
