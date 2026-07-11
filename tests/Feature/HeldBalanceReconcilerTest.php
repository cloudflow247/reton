<?php

declare(strict_types=1);

use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Services\HeldBalanceReconciler;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('repairs drifted held_balance from active holds', function () {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();
    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(1_000_00, 'NGN'));

    app(TransferService::class)->sendProtected(
        $sender,
        $from->refresh(),
        $to,
        Money::of(300_00, 'NGN'),
        null,
        'held-reconcile-1',
    );

    // Simulate soft-hold drift (production incident class).
    $to->refresh()->forceFill(['held_balance' => 50_00])->save();

    expect(app(HeldBalanceReconciler::class)->isConsistent($to->fresh()))->toBeFalse();

    $synced = app(HeldBalanceReconciler::class)->sync($to->fresh());

    expect($synced)->toBe(30000)
        ->and($to->fresh()->held_balance)->toBe(30000)
        ->and(app(HeldBalanceReconciler::class)->isConsistent($to->fresh()))->toBeTrue()
        ->and($to->fresh()->availableMinor() + $to->fresh()->heldMinor())->toBe($to->fresh()->ledgerMinor());
});
