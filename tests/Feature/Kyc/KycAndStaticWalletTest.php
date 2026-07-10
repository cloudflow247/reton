<?php

declare(strict_types=1);

use App\Domain\Kyc\Enums\KycTier;
use App\Domain\Kyc\Services\KycService;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.alatpay.business_bvn', '22222222222');
    config([
        'services.kyc.bvn_provider' => 'dojah',
        'services.dojah.driver' => 'fake',
    ]);
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway);
});

it('starts users at tier 1 with collection static wallet mapping', function () {
    $user = User::factory()->create();
    $kyc = app(KycService::class)->forUser($user);

    expect($kyc->tier)->toBe(KycTier::Tier1)
        ->and($kyc->staticWalletType())->toBe(StaticWalletType::Collection);
});

it('upgrades to tier 2 with bvn and unlocks individual static wallet type', function () {
    $user = User::factory()->create(['name' => 'Reton Test User']);

    $kyc = app(KycService::class)->upgradeToTier2($user, '22334455667', '1990-05-15');

    expect($kyc->tier)->toBe(KycTier::Tier2)
        ->and($kyc->bvn_last4)->toBe('5667')
        ->and($kyc->staticWalletType())->toBe(StaticWalletType::Individual);
});

it('upgrades to tier 3 with nin and address', function () {
    $user = User::factory()->create(['name' => 'Reton Test User']);
    app(KycService::class)->upgradeToTier2($user, '22334455667', '1990-05-15');

    $kyc = app(KycService::class)->upgradeToTier3(
        $user,
        '12345678901',
        '12 Admiralty Way',
        'Lekki',
        'Lagos',
    );

    expect($kyc->tier)->toBe(KycTier::Tier3)
        ->and($kyc->nin_last4)->toBe('8901')
        ->and($kyc->city)->toBe('Lekki');
});

it('provisions an individual static account when bvn is verified', function () {
    [$user, $wallet] = readyUserWithWallet();

    $this->actingAs($user)->post('/static-account', ['wallet_id' => $wallet->id])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(StaticAccount::query()->where('wallet_id', $wallet->id)->first()?->wallet_type)->toBe(StaticWalletType::Individual);
});

it('returns a validation error instead of 500 when alatpay provision fails', function () {
    config([
        'services.kyc.bvn_provider' => 'alatpay',
        'services.alatpay.driver' => 'http',
        'services.alatpay.api_key' => 'bad-key',
        'services.alatpay.merchant_email' => null,
        'services.alatpay.merchant_password' => null,
        'services.alatpay.business_id' => 'biz',
        'services.alatpay.base_url' => 'https://apibox.alatpay.ng',
    ]);

    app()->forgetInstance(AlatpayGateway::class);

    Http::fake([
        'apibox.alatpay.ng/*' => Http::response(['message' => 'Access denied'], 401),
    ]);

    [$user, $wallet] = readyUserWithWallet();

    $this->actingAs($user)->from('/add-money')->post('/static-account', ['wallet_id' => $wallet->id])
        ->assertRedirect('/add-money')
        ->assertSessionHasErrors('wallet');

    expect(StaticAccount::query()->where('wallet_id', $wallet->id)->exists())->toBeFalse();
});

it('renders add money with kyc and static account props', function () {
    $user = User::factory()->create();
    app(WalletService::class)->open($user, 'NGN');

    $this->actingAs($user)->get('/add-money')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AddMoney')
            ->has('kyc')
            ->where('kyc.tier', 1)
            ->has('staticAccount'));
});

it('renders profile with kyc props', function () {
    $user = readyUser();

    $this->actingAs($user)->get('/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Profile')
            ->has('kyc')
            ->where('kyc.tier', 1));
});

it('enforces kyc limits on deposits', function () {
    $user = User::factory()->create();
    $kyc = app(KycService::class)->forUser($user);
    $kyc->storeBvn('22334455667');
    $kyc->update(['bvn_verified_at' => now()]);
    $wallet = app(WalletService::class)->open($user, 'NGN');

    $this->actingAs($user)->post('/deposits', [
        'wallet_id' => $wallet->id,
        'amount' => 60_000_00,
        'method' => 'bank_transfer',
    ])->assertSessionHasErrors('amount');
});
