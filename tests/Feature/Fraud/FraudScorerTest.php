<?php

declare(strict_types=1);

use App\Domain\Auth\Models\Device;
use App\Domain\Fraud\Contracts\FraudScorer;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Enums\FraudAction;
use App\Domain\Fraud\Enums\FraudRiskLevel;
use App\Domain\Fraud\Models\FraudAlert;
use App\Domain\Fraud\Services\FraudService;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function scorer(): FraudScorer
{
    return app(FraudScorer::class);
}

/**
 * @return array{0: User, 1: Wallet}
 */
function fraudActor(int $fund = 1_000_000_00): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    if ($fund > 0) {
        app(WalletService::class)->fund($wallet, Money::of($fund, 'NGN'));
    }

    return [$user, $wallet->refresh()];
}

function frCtx(User $user, Wallet $wallet, int $amount, array $overrides = []): FraudContext
{
    return new FraudContext(
        user: $user,
        wallet: $wallet,
        amount: Money::of($amount, 'NGN'),
        action: $overrides['action'] ?? 'transfer',
        beneficiary: $overrides['beneficiary'] ?? null,
        deviceFingerprint: $overrides['device'] ?? null,
        ipAddress: $overrides['ip'] ?? null,
    );
}

it('scores an unremarkable transaction as low risk and allows it', function () {
    [$user, $wallet] = fraudActor();

    $assessment = scorer()->score(frCtx($user, $wallet, 1_00));

    expect($assessment->level)->toBe(FraudRiskLevel::Low)
        ->and($assessment->action)->toBe(FraudAction::Allow)
        ->and($assessment->isBlocked())->toBeFalse();
});

it('flags a large amount and raises the score', function () {
    config(['reton.fraud.large_amount_threshold' => 5_000_00, 'reton.fraud.large_amount_points' => 50]);
    [$user, $wallet] = fraudActor();

    $assessment = scorer()->score(frCtx($user, $wallet, 10_000_00));

    expect($assessment->score)->toBeGreaterThanOrEqual(50)
        ->and($assessment->reasons())->toContain('large_amount');
});

it('flags an unrecognised device', function () {
    config(['reton.fraud.new_device_points' => 30]);
    [$user, $wallet] = fraudActor();

    $assessment = scorer()->score(frCtx($user, $wallet, 1_00, ['device' => 'unknown-fp']));

    expect($assessment->reasons())->toContain('new_device');
});

it('does not flag a device the user has used before', function () {
    config(['reton.fraud.new_device_points' => 30]);
    [$user, $wallet] = fraudActor();
    Device::create(['user_id' => $user->id, 'fingerprint' => 'known-fp', 'name' => 'phone']);

    $assessment = scorer()->score(frCtx($user, $wallet, 1_00, ['device' => 'known-fp']));

    expect($assessment->reasons())->not->toContain('new_device');
});

it('flags repeated failed PIN attempts', function () {
    config(['reton.fraud.failed_pin_threshold' => 3, 'reton.fraud.failed_pin_points' => 35]);
    [$user, $wallet] = fraudActor();
    $user->forceFill(['pin_attempts' => 4])->save();

    $assessment = scorer()->score(frCtx($user->fresh(), $wallet, 1_00));

    expect($assessment->reasons())->toContain('failed_pins');
});

it('flags high transfer velocity', function () {
    config(['reton.fraud.velocity_max_count' => 2, 'reton.fraud.velocity_points' => 40]);
    [$user, $wallet] = fraudActor();
    [, $to] = fraudActor(0);

    // Three transfers in quick succession breaches the velocity threshold.
    foreach (range(1, 3) as $ignored) {
        app(TransferService::class)->sendNormal($user, $wallet->fresh(), $to, Money::of(1_00, 'NGN'));
    }

    $assessment = scorer()->score(frCtx($user, $wallet->fresh(), 1_00));

    expect($assessment->reasons())->toContain('high_velocity');
});

it('maps stacked signals to a high-risk blocking action', function () {
    config([
        'reton.fraud.large_amount_threshold' => 5_000_00,
        'reton.fraud.large_amount_points' => 50,
        'reton.fraud.new_device_points' => 45,
        'reton.fraud.high_min' => 70,
    ]);
    [$user, $wallet] = fraudActor();

    $assessment = scorer()->score(frCtx($user, $wallet, 10_000_00, ['device' => 'unknown-fp']));

    expect($assessment->score)->toBeGreaterThanOrEqual(70)
        ->and($assessment->level)->toBe(FraudRiskLevel::High)
        ->and($assessment->isBlocked())->toBeTrue();
});

it('records a fraud alert when an assessment is flagged', function () {
    config(['reton.fraud.large_amount_threshold' => 5_000_00, 'reton.fraud.large_amount_points' => 50]);
    [$user, $wallet] = fraudActor();

    $assessment = app(FraudService::class)->evaluate(frCtx($user, $wallet, 10_000_00));

    expect($assessment->isFlagged())->toBeTrue()
        ->and(FraudAlert::where('user_id', $user->id)->exists())->toBeTrue();
});

it('does not record an alert for an allowed transaction', function () {
    [$user, $wallet] = fraudActor();

    app(FraudService::class)->evaluate(frCtx($user, $wallet, 1_00));

    expect(FraudAlert::count())->toBe(0);
});
