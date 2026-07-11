<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.alatpay.business_bvn', '22222222222');
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway);
});

/** @return array{0: User, 1: Wallet} */
function apiStaticOwner(): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    return [$user, $wallet];
}

it('provisions an individual static account and returns pending_otp', function () {
    [$user, $wallet] = apiStaticOwner();

    $this->actingAs($user)->postJson('/api/v1/static-accounts', [
        'wallet_id' => $wallet->id,
        'wallet_type' => 'individual',
        'bvn' => '12345678901',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending_otp')
        ->assertJsonPath('data.wallet_type', 'individual');
});

it('requires a bvn for individual wallets', function () {
    [$user, $wallet] = apiStaticOwner();

    $this->actingAs($user)->postJson('/api/v1/static-accounts', [
        'wallet_id' => $wallet->id,
        'wallet_type' => 'individual',
    ])->assertStatus(422);
});

it('rejects a bvn on a collection wallet', function () {
    [$user, $wallet] = apiStaticOwner();

    $this->actingAs($user)->postJson('/api/v1/static-accounts', [
        'wallet_id' => $wallet->id,
        'wallet_type' => 'collection',
        'bvn' => '12345678901',
    ])->assertStatus(422);
});

it('forbids provisioning against a wallet the user does not own', function () {
    [, $wallet] = apiStaticOwner();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->postJson('/api/v1/static-accounts', [
        'wallet_id' => $wallet->id,
        'wallet_type' => 'collection',
    ])->assertStatus(403);
});

it('verifies a pending account via OTP and returns the account number', function () {
    [$user, $wallet] = apiStaticOwner();
    $account = app(StaticAccountService::class)->provision($user, $wallet, StaticWalletType::Individual, '12345678901');

    $this->actingAs($user)->postJson('/api/v1/static-accounts/'.$account->id.'/verify', [
        'otp' => '123456',
    ])->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonStructure(['data' => ['account_number']]);
});

it('lists only the callers static accounts', function () {
    [$user, $wallet] = apiStaticOwner();
    app(StaticAccountService::class)->provision($user, $wallet, StaticWalletType::Individual, '12345678901');
    [$other, $otherWallet] = apiStaticOwner();
    // Distinct BVN — ALATPay (and the fake) reject reusing one BVN under a different email.
    app(StaticAccountService::class)->provision($other, $otherWallet, StaticWalletType::Individual, '10987654321');

    $this->actingAs($user)->getJson('/api/v1/static-accounts')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('forbids viewing someone elses static account', function () {
    [$user, $wallet] = apiStaticOwner();
    $account = app(StaticAccountService::class)->provision($user, $wallet, StaticWalletType::Individual, '12345678901');
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->getJson('/api/v1/static-accounts/'.$account->id)
        ->assertStatus(403);
});
