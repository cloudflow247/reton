<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('credits funded transactions across active static accounts', function () {
    config()->set('services.alatpay.business_bvn', '22222222222');
    $gateway = new FakeAlatpayGateway;
    $this->app->instance(AlatpayGateway::class, $gateway);

    $user = User::factory()->create();
    ensureVerifiedBvn($user);
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $svc = app(StaticAccountService::class);
    $account = $svc->verify($svc->provision($user, $wallet, StaticWalletType::Individual, '12345678901'), '123456');

    $gateway->markStaticFunded($account->account_number, 75.00, 'txn-cmd');

    $this->artisan('static-accounts:poll')->assertExitCode(0);

    expect($wallet->fresh()->balance)->toBe(7500);
});

it('continues polling other accounts when one credit fails', function () {
    config()->set('services.alatpay.business_bvn', '22222222222');
    $gateway = new FakeAlatpayGateway;
    $this->app->instance(AlatpayGateway::class, $gateway);

    $blocked = User::factory()->create();
    $ok = User::factory()->create();
    ensureVerifiedBvn($ok);

    $walletBlocked = app(WalletService::class)->open($blocked, 'NGN');
    $walletOk = app(WalletService::class)->open($ok, 'NGN');
    $svc = app(StaticAccountService::class);

    $accountBlocked = $svc->verify(
        $svc->provision($blocked, $walletBlocked, StaticWalletType::Individual, '11111111111'),
        '123456',
    );
    $accountOk = $svc->verify(
        $svc->provision($ok, $walletOk, StaticWalletType::Individual, '22222222222'),
        '123456',
    );

    // Tier 1 single-tx max is ₦20k - this credit fails KYC and must not abort the command.
    $gateway->markStaticFunded($accountBlocked->account_number, 25_000.00, 'txn-blocked');
    $gateway->markStaticFunded($accountOk->account_number, 80.00, 'txn-ok');

    $this->artisan('static-accounts:poll')->assertExitCode(0);

    expect($walletBlocked->fresh()->balance)->toBe(0)
        ->and($walletOk->fresh()->balance)->toBe(8000);
});
