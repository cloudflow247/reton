<?php

declare(strict_types=1);

use App\Domain\Dashboard\Services\DashboardSummaryService;
use App\Domain\Fraud\Models\FraudAlert;
use App\Domain\Recovery\Services\RecoveryService;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('aggregates trust metrics for the dashboard', function () {
    $sender = User::factory()->create(['transaction_pin' => Hash::make('1234')]);
    $receiver = User::factory()->create(['transaction_pin' => Hash::make('5678')]);
    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(2_000_00, 'NGN'));

    $protected = app(TransferService::class)->sendProtected(
        $sender,
        $from->refresh(),
        $to,
        Money::of(400_00, 'NGN'),
    );

    $this->actingAs($sender)->postJson("/api/v1/transfers/{$protected->id}/callbacks", [
        'reason' => 'Goods not delivered',
        'pin' => '1234',
    ])->assertCreated();

    $normal = app(TransferService::class)->sendNormal(
        $sender,
        $from->refresh(),
        $to,
        Money::of(200_00, 'NGN'),
    );

    app(RecoveryService::class)->report($normal->fresh(), $sender, 'Wrong person');

    FraudAlert::query()->create([
        'user_id' => $sender->id,
        'wallet_id' => $from->id,
        'action_context' => 'transfer',
        'score' => 72,
        'level' => 'medium',
        'recommended_action' => 'challenge',
        'signals' => ['velocity'],
        'status' => 'open',
    ]);

    $summary = app(DashboardSummaryService::class)->forUser($sender->fresh());

    expect($summary->pending_callbacks)->toBe(1)
        ->and($summary->protected_transfers_pending)->toBe(1)
        ->and($summary->open_recoveries)->toBe(1)
        ->and($summary->open_fraud_alerts)->toBe(1)
        ->and($summary->trust_score)->toBe(78);
});

it('includes trust summary on the dashboard page', function () {
    $sender = User::factory()->create(['transaction_pin' => Hash::make('1234')]);
    $receiver = User::factory()->create();
    $from = app(WalletService::class)->open($sender, 'NGN');
    $to = app(WalletService::class)->open($receiver, 'NGN');
    app(WalletService::class)->fund($from, Money::of(500_00, 'NGN'));

    app(TransferService::class)->sendProtected($sender, $from->refresh(), $to, Money::of(100_00, 'NGN'));

    $this->actingAs($sender)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('summary')
            ->where('summary.protected_transfers_pending', 1)
            ->where('summary.trust_score', 100));
});
