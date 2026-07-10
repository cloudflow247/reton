<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Admin\AdminPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('syncs settled static deposits from the admin integrations action', function () {
    config()->set('services.alatpay.driver', 'http');
    config()->set('services.alatpay.business_bvn', '22222222222');

    $gateway = new FakeAlatpayGateway;
    $this->app->instance(AlatpayGateway::class, $gateway);

    $admin = readyUser(['is_admin' => true]);
    $user = readyUser();
    ensureVerifiedBvn($user);
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $svc = app(StaticAccountService::class);
    $account = $svc->verify(
        $svc->provision($user, $wallet, StaticWalletType::Individual, '12345678901'),
        '123456',
    );
    $gateway->markStaticFunded($account->account_number, 150.00, 'txn-admin-sync');

    $this->actingAs($admin)
        ->post(AdminPath::url('integrations/alatpay/sync-deposits'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($wallet->fresh()->balance)->toBe(15000);
});

it('warns when alatpay driver is fake during sync', function () {
    config()->set('services.alatpay.driver', 'fake');

    $admin = readyUser(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(AdminPath::url('integrations/alatpay/sync-deposits'))
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('forces alatpay driver to http in production when credentials exist', function () {
    app()->detectEnvironment(fn () => 'production');

    Log::spy();

    $values = (new ReflectionClass(PlatformSettingsService::class))
        ->getMethod('normalizeAlatpayRuntime');
    $values->setAccessible(true);

    $normalized = $values->invoke(app(PlatformSettingsService::class), [
        'driver' => 'fake',
        'merchant_email' => 'pay@cloudflow.agency',
        'merchant_password' => 'secret',
        'business_id' => 'biz-1',
        'base_url' => 'https://api.alatpay.ng',
    ]);

    expect($normalized['driver'])->toBe('http')
        ->and($normalized['base_url'])->toBe('https://apibox.alatpay.ng');
});
