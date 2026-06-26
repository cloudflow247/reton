<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Exceptions\AlatpayException;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.alatpay.business_bvn', '22222222222');
    $this->gateway = new FakeAlatpayGateway;
    $this->app->instance(AlatpayGateway::class, $this->gateway);
});

function staticAccounts(): StaticAccountService
{
    return app(StaticAccountService::class);
}

/** @return array{0: User, 1: Wallet} */
function staticOwner(): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    return [$user, $wallet];
}

it('provisions an individual static account in pending_otp state', function () {
    [$user, $wallet] = staticOwner();

    $account = staticAccounts()->provision($user, $wallet, StaticWalletType::Individual, '12345678901');

    expect($account->status)->toBe(StaticAccountStatus::PendingOtp)
        ->and($account->wallet_type)->toBe(StaticWalletType::Individual)
        ->and($account->provider_reference)->not->toBeNull()
        ->and($account->otp_tracking_id)->not->toBeNull()
        ->and($account->account_number)->toBeNull();
});

it('verifies an account with the correct OTP and activates it', function () {
    [$user, $wallet] = staticOwner();
    $account = staticAccounts()->provision($user, $wallet, StaticWalletType::Individual, '12345678901');

    $account = staticAccounts()->verify($account, '123456');

    expect($account->status)->toBe(StaticAccountStatus::Active)
        ->and($account->account_number)->not->toBeEmpty();
});

it('leaves the account pending when the OTP is wrong', function () {
    [$user, $wallet] = staticOwner();
    $account = staticAccounts()->provision($user, $wallet, StaticWalletType::Individual, '12345678901');

    try {
        staticAccounts()->verify($account, '000000');
    } catch (AlatpayException) {
        // expected
    }

    expect($account->fresh()->status)->toBe(StaticAccountStatus::PendingOtp);
});

it('activates immediately when the provider returns an account number without an OTP', function () {
    [$user, $wallet] = staticOwner();
    $this->gateway->provisionReturnsImmediately(true);

    $account = staticAccounts()->provision($user, $wallet, StaticWalletType::Collection, null);

    expect($account->status)->toBe(StaticAccountStatus::Active)
        ->and($account->account_number)->not->toBeEmpty();
});
