<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\StaticAccountRequest;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Data\ProviderContactRebindResult;
use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Payments\Services\ProviderContactRebindService;
use App\Support\Banking\ProviderContactEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.alatpay.driver' => 'fake',
        'services.alatpay.merchant_email' => 'ceo@cloudfow.example',
        'services.alatpay.business_id' => 'biz-test',
    ]);

    $fake = new FakeAlatpayGateway;
    $fake->reset();
    $fake->provisionReturnsImmediately(true);
    $this->app->instance(AlatpayGateway::class, $fake);
    $this->app->instance(FakeAlatpayGateway::class, $fake);
});

it('rebinds a legacy customer provider email onto the ceo merchant alias', function () {
    [$user, $wallet] = readyUserWithWallet();

    /** @var FakeAlatpayGateway $gateway */
    $gateway = app(FakeAlatpayGateway::class);

    $provision = $gateway->provisionStaticAccount(new StaticAccountRequest(
        walletType: StaticWalletType::Individual->providerCode(),
        bvn: '22123456789',
        email: (string) $user->email,
        reference: 'LEGACY-REBIND-1',
    ));

    StaticAccount::query()->create([
        'wallet_id' => $wallet->getKey(),
        'user_id' => $user->getKey(),
        'provider' => 'alatpay',
        'provider_reference' => $provision->staticWalletId,
        'wallet_type' => StaticWalletType::Individual,
        'status' => StaticAccountStatus::Active,
        'account_number' => $provision->accountNumber,
        'account_name' => 'RETON STATIC',
        'bank_name' => 'ALAT by Wema',
        'email' => $user->email,
    ]);

    $desired = ProviderContactEmail::forUser($user);

    $result = app(ProviderContactRebindService::class)->rebindForUser($user);

    expect($result->status)->toBe(ProviderContactRebindResult::STATUS_REBOUND)
        ->and($result->desiredProviderEmail)->toBe($desired)
        ->and($result->previousProviderEmail)->toBe(strtolower((string) $user->email));

    $listed = collect($gateway->listStaticAccounts())
        ->first(fn ($row) => $row->id === $provision->staticWalletId);

    expect($listed)->not->toBeNull()
        ->and(strtolower((string) $listed->email))->toBe(strtolower($desired));

    $meta = StaticAccount::query()->where('provider_reference', $provision->staticWalletId)->firstOrFail()->metadata;
    expect($meta['provider_email'] ?? null)->toBe($desired)
        ->and($meta['provider_email_synced'] ?? false)->toBeTrue();
});

it('reports already_ok when the provider email is already the ceo alias', function () {
    [$user, $wallet] = readyUserWithWallet();
    $desired = ProviderContactEmail::forUser($user);

    /** @var FakeAlatpayGateway $gateway */
    $gateway = app(FakeAlatpayGateway::class);

    $provision = $gateway->provisionStaticAccount(new StaticAccountRequest(
        walletType: StaticWalletType::Individual->providerCode(),
        bvn: '22987654321',
        email: $desired,
        reference: 'ALREADY-OK-1',
    ));

    StaticAccount::query()->create([
        'wallet_id' => $wallet->getKey(),
        'user_id' => $user->getKey(),
        'provider' => 'alatpay',
        'provider_reference' => $provision->staticWalletId,
        'wallet_type' => StaticWalletType::Individual,
        'status' => StaticAccountStatus::Active,
        'account_number' => $provision->accountNumber,
        'email' => $user->email,
    ]);

    $result = app(ProviderContactRebindService::class)->rebindForUser($user);

    expect($result->status)->toBe(ProviderContactRebindResult::STATUS_ALREADY_OK);
});

it('dry-runs without mutating the provider email', function () {
    [$user, $wallet] = readyUserWithWallet();

    /** @var FakeAlatpayGateway $gateway */
    $gateway = app(FakeAlatpayGateway::class);

    $provision = $gateway->provisionStaticAccount(new StaticAccountRequest(
        walletType: StaticWalletType::Individual->providerCode(),
        bvn: '22555555555',
        email: (string) $user->email,
        reference: 'DRY-RUN-1',
    ));

    StaticAccount::query()->create([
        'wallet_id' => $wallet->getKey(),
        'user_id' => $user->getKey(),
        'provider' => 'alatpay',
        'provider_reference' => $provision->staticWalletId,
        'wallet_type' => StaticWalletType::Individual,
        'status' => StaticAccountStatus::Active,
        'account_number' => $provision->accountNumber,
        'email' => $user->email,
    ]);

    $result = app(ProviderContactRebindService::class)->rebindForUser($user, dryRun: true);

    expect($result->status)->toBe(ProviderContactRebindResult::STATUS_DRY_RUN);

    $listed = collect($gateway->listStaticAccounts())
        ->first(fn ($row) => $row->id === $provision->staticWalletId);

    expect(strtolower((string) $listed->email))->toBe(strtolower((string) $user->email));
});

it('exposes the artisan support command', function () {
    [$user, $wallet] = readyUserWithWallet();

    /** @var FakeAlatpayGateway $gateway */
    $gateway = app(FakeAlatpayGateway::class);

    $provision = $gateway->provisionStaticAccount(new StaticAccountRequest(
        walletType: StaticWalletType::Individual->providerCode(),
        bvn: '22444444444',
        email: (string) $user->email,
        reference: 'ARTISAN-1',
    ));

    StaticAccount::query()->create([
        'wallet_id' => $wallet->getKey(),
        'user_id' => $user->getKey(),
        'provider' => 'alatpay',
        'provider_reference' => $provision->staticWalletId,
        'wallet_type' => StaticWalletType::Individual,
        'status' => StaticAccountStatus::Active,
        'account_number' => $provision->accountNumber,
        'email' => $user->email,
    ]);

    $this->artisan('payments:rebind-provider-email', [
        'email' => $user->email,
        '--force' => true,
    ])->assertSuccessful();
});
