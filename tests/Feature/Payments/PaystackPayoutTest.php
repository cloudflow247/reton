<?php

declare(strict_types=1);

use App\Domain\Payments\Contracts\PayoutGateway;
use App\Domain\Payments\Enums\PayoutStatus;
use App\Domain\Payments\Paystack\Gateways\FakePaystackPayoutGateway;
use App\Domain\Payments\Services\PayoutService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'reton.payouts.provider' => 'paystack',
        'reton.features.withdraw' => true,
        'services.paystack.driver' => 'fake',
    ]);

    $this->gateway = new FakePaystackPayoutGateway;
    $this->app->instance(PayoutGateway::class, $this->gateway);
});

it('initiates a Paystack payout with a PSK transfer reference', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    app(WalletService::class)->fund($wallet, Money::of(50_000_00, 'NGN'));

    $payout = app(PayoutService::class)->request(
        $user,
        $wallet->refresh(),
        Money::of(5_000_00, 'NGN'),
        '058',
        '0123456789',
        $user->name,
    );

    expect($payout->provider)->toBe('paystack')
        ->and($payout->status)->toBe(PayoutStatus::Pending)
        ->and($payout->provider_reference)->toStartWith('PSK-TRF-');
});

it('exposes paystack as the configured payout provider', function () {
    expect(app(PayoutService::class)->provider())->toBe('paystack')
        ->and(app(PayoutGateway::class)->supportsOutboundTransfers())->toBeTrue();
});
