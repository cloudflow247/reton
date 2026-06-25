<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.alatpay.business_bvn', '22222222222');
    $this->gateway = new FakeAlatpayGateway();
    $this->app->instance(AlatpayGateway::class, $this->gateway);
});

/** @return array{0: \App\Domain\Payments\Models\StaticAccount, 1: \App\Domain\Wallet\Models\Wallet} */
function activeStaticAccount(): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $svc = app(StaticAccountService::class);
    $account = $svc->provision($user, $wallet, StaticWalletType::Individual, '12345678901');
    $account = $svc->verify($account, '123456');

    return [$account, $wallet];
}

it('credits the wallet for a new successful transaction, converting major to minor units', function () {
    [$account, $wallet] = activeStaticAccount();
    $this->gateway->markStaticFunded($account->account_number, 100.00, 'txn-1');

    $credited = app(StaticAccountService::class)->poll($account);

    expect($credited)->toBe(1)
        ->and($wallet->fresh()->balance)->toBe(10000) // NGN 100.00 -> 10000 kobo
        ->and(Deposit::where('provider', 'alatpay_static')->where('provider_reference', 'txn-1')->count())->toBe(1);
});

it('does not double-credit when the same transaction is polled twice', function () {
    [$account, $wallet] = activeStaticAccount();
    $this->gateway->markStaticFunded($account->account_number, 100.00, 'txn-dup');

    app(StaticAccountService::class)->poll($account);
    $second = app(StaticAccountService::class)->poll($account->fresh());

    expect($second)->toBe(0)
        ->and($wallet->fresh()->balance)->toBe(10000);
});

it('ignores transactions that are not successful', function () {
    [$account, $wallet] = activeStaticAccount();
    // status 2 = not successful; status 1 = successful. Only the successful one credits.
    $this->gateway->recordStaticTransaction($account->account_number, 2, 999.00, 'txn-failed');
    $this->gateway->recordStaticTransaction($account->account_number, 1, 50.00, 'txn-ok');

    $credited = app(StaticAccountService::class)->poll($account);

    expect($credited)->toBe(1)
        ->and($wallet->fresh()->balance)->toBe(5000); // only the status==1 txn credited
});

it('stamps last_polled_at', function () {
    [$account] = activeStaticAccount();

    app(StaticAccountService::class)->poll($account);

    expect($account->fresh()->last_polled_at)->not->toBeNull();
});

it('does not credit a successful transaction with a zero amount', function () {
    [$account, $wallet] = activeStaticAccount();
    $this->gateway->recordStaticTransaction($account->account_number, 1, 0.00, 'txn-zero');

    $credited = app(StaticAccountService::class)->poll($account);

    expect($credited)->toBe(0)
        ->and($wallet->fresh()->balance)->toBe(0);
});
