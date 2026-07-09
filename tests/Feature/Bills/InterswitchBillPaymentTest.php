<?php

declare(strict_types=1);

use App\Domain\Bills\Enums\BillCategory;
use App\Domain\Bills\Remita\Contracts\BillProviderGateway;
use App\Domain\Bills\Remita\Gateways\FakeBillProvider;
use App\Domain\Bills\Services\BillPaymentService;
use App\Domain\Bills\Support\BillPaymentCodeResolver;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'reton.bills.provider' => 'interswitch',
        'services.interswitch.driver' => 'fake',
    ]);
    $this->app->instance(BillProviderGateway::class, new FakeBillProvider);
});

it('uses interswitch as the default bill provider', function () {
    expect(config('reton.bills.provider'))->toBe('interswitch');
});

it('pays airtime via interswitch fake gateway', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    app(WalletService::class)->fund($wallet, Money::of(5_000_00, 'NGN'));

    $bill = app(BillPaymentService::class)->pay(
        $user,
        $wallet,
        BillCategory::Airtime,
        'mtn',
        'MTN',
        '08030000000',
        Money::of(500_00, 'NGN'),
    );

    expect($bill->provider)->toBe('interswitch')
        ->and($bill->metadata['payment_code'] ?? null)->toBe('628051043');
});

it('normalizes legacy 9mobile biller code to t2', function () {
    expect(BillPaymentCodeResolver::normalizeCode('9mobile'))->toBe('t2')
        ->and(BillPaymentCodeResolver::resolve('9mobile', BillCategory::Airtime))->toBe('6280510426');

    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    app(WalletService::class)->fund($wallet, Money::of(5_000_00, 'NGN'));

    $bill = app(BillPaymentService::class)->pay(
        $user,
        $wallet,
        BillCategory::Airtime,
        '9mobile',
        '9mobile',
        '08090000000',
        Money::of(500_00, 'NGN'),
    );

    expect($bill->biller_code)->toBe('t2')
        ->and($bill->biller_name)->toBe('T2');
});

it('stores interswitch credentials encrypted in admin settings', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post('/admin/integrations/save', [
        'integration' => 'interswitch',
        'driver' => 'http',
        'passport_url' => 'https://passport.interswitchng.com/passport/oauth/token',
        'base_url' => 'https://interswitchng.com/quicktellerservice/api/v5',
        'terminal_id' => '3PBL0001',
        'client_id' => 'IKIA_TEST_CLIENT_ID',
        'client_secret' => 'super_secret_interswitch',
        'request_reference_prefix' => '1453',
        'timeout' => 15,
    ])->assertRedirect();

    expect(app(PlatformSettingsService::class)->isIntegrationReady('interswitch'))->toBeTrue()
        ->and(config('services.interswitch.terminal_id'))->toBe('3PBL0001');
});

it('allows rrr lookup via remita when remita fake driver is ready', function () {
    config(['services.remita.driver' => 'fake']);

    $inquiry = app(BillPaymentService::class)->lookupRrr('100000000001');

    expect($inquiry->rrr)->toBe('100000000001');
});
