<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\DepositStatus;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Services\AlatpayDepositService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('credits deposits that AlatPay confirms but whose webhook was missed', function () {
    $gateway = new FakeAlatpayGateway();
    $this->app->instance(AlatpayGateway::class, $gateway);

    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $deposit = app(AlatpayDepositService::class)->initiate($user, $wallet, Money::of(500_00, 'NGN'));

    // Age the deposit past the reconcile threshold and confirm payment provider-side.
    $deposit->forceFill(['created_at' => now()->subMinutes(10)])->save();
    $gateway->markPaid($deposit->provider_reference, 50000);

    $this->artisan('deposits:reconcile')->assertSuccessful();

    expect(Deposit::find($deposit->id)->status)->toBe(DepositStatus::Completed)
        ->and($wallet->fresh()->balance)->toBe(50000);
});
