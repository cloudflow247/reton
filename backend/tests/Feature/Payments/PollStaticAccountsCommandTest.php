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
    $gateway = new FakeAlatpayGateway();
    $this->app->instance(AlatpayGateway::class, $gateway);

    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $svc = app(StaticAccountService::class);
    $account = $svc->verify($svc->provision($user, $wallet, StaticWalletType::Individual, '12345678901'), '123456');

    $gateway->markStaticFunded($account->account_number, 75.00, 'txn-cmd');

    $this->artisan('static-accounts:poll')->assertExitCode(0);

    expect($wallet->fresh()->balance)->toBe(7500);
});
