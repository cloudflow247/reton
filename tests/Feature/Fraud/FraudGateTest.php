<?php

declare(strict_types=1);

use App\Domain\Fraud\Models\FraudAlert;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Wallet}
 */
function gateActor(int $fund): array
{
    $user = User::factory()->create(['transaction_pin' => Hash::make('1234')]);
    $wallet = app(WalletService::class)->open($user, 'NGN');
    app(WalletService::class)->fund($wallet, Money::of($fund, 'NGN'));

    return [$user, $wallet->refresh()];
}

it('blocks a high-risk transfer and records a fraud alert', function () {
    // Large amount + unrecognised device stacks past the high-risk threshold.
    config([
        'reton.fraud.large_amount_threshold' => 5_000_00,
        'reton.fraud.large_amount_points' => 50,
        'reton.fraud.new_device_points' => 45,
    ]);

    [$sender, $from] = gateActor(1_000_000_00);
    [, $to] = gateActor(0 + 1); // receiver wallet only

    $response = $this->actingAs($sender)
        ->withHeaders(['X-Device-Fingerprint' => 'brand-new-device'])
        ->postJson('/api/v1/transfers', [
            'from_wallet_id' => $from->id,
            'to_wallet_id' => $to->id,
            'amount' => 50_000_00,
            'type' => 'normal',
            'pin' => '1234',
        ]);

    $response->assertStatus(403)->assertJsonPath('code', 'fraud_blocked');

    expect($from->fresh()->balance)->toBe(100000000) // unchanged — transfer blocked
        ->and(FraudAlert::where('user_id', $sender->id)->where('level', 'high')->exists())->toBeTrue();
});

it('allows an ordinary transfer with default thresholds', function () {
    [$sender, $from] = gateActor(1_000_00);
    [, $to] = gateActor(1);

    $this->actingAs($sender)->postJson('/api/v1/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 100_00,
        'type' => 'normal',
        'pin' => '1234',
    ])->assertCreated()->assertJsonPath('data.status', 'completed');

    expect($from->fresh()->balance)->toBe(90000);
});
