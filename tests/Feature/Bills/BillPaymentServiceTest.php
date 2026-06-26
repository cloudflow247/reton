<?php

declare(strict_types=1);

use App\Domain\Bills\Enums\BillCategory;
use App\Domain\Bills\Enums\BillStatus;
use App\Domain\Bills\Remita\Contracts\BillProviderGateway;
use App\Domain\Bills\Remita\Exceptions\BillProviderException;
use App\Domain\Bills\Remita\Gateways\FakeBillProvider;
use App\Domain\Bills\Services\BillPaymentService;
use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gateway = new FakeBillProvider;
    $this->app->instance(BillProviderGateway::class, $this->gateway);
});

function billService(): BillPaymentService
{
    return app(BillPaymentService::class);
}

/**
 * @return array{0: User, 1: Wallet}
 */
function billPayer(int $fund = 1_000_00): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    if ($fund > 0) {
        app(WalletService::class)->fund($wallet, Money::of($fund, 'NGN'));
    }

    return [$user, $wallet->refresh()];
}

function billSettlementMinor(): int
{
    return app(SystemAccountResolver::class)->resolve(SystemAccount::SettlementPayable, 'NGN')->balanceMinor();
}

function payAirtime($user, $wallet, int $amount)
{
    return billService()->pay($user, $wallet, BillCategory::Airtime, 'mtn', 'MTN', '08030000000', Money::of($amount, 'NGN'));
}

it('settles a bill immediately when the provider confirms it', function () {
    [$user, $wallet] = billPayer(1_000_00);

    $bill = payAirtime($user, $wallet, 200_00);

    expect($bill->status)->toBe(BillStatus::Completed)
        ->and($bill->reservation_transaction_id)->not->toBeNull()
        ->and($bill->settlement_transaction_id)->not->toBeNull()
        ->and($bill->provider_reference)->not->toBeNull()
        ->and($wallet->fresh()->balance)->toBe(80000)  // money has left the wallet
        ->and(billSettlementMinor())->toBe(0);          // settlement cleared
});

it('refuses a bill that exceeds the available balance', function () {
    [$user, $wallet] = billPayer(100_00);

    payAirtime($user, $wallet, 500_00);
})->throws(InsufficientFundsException::class);

it('reverses the bill and restores the wallet when the provider declines', function () {
    [$user, $wallet] = billPayer(1_000_00);
    $this->gateway->willReturn('failed');

    $bill = payAirtime($user, $wallet, 200_00);

    expect($bill->status)->toBe(BillStatus::Failed)
        ->and($bill->failure_reason)->not->toBeNull()
        ->and($wallet->fresh()->balance)->toBe(100000)  // funds returned
        ->and(billSettlementMinor())->toBe(0);
});

it('leaves a bill pending and reconciles it once the provider confirms', function () {
    [$user, $wallet] = billPayer(1_000_00);
    $this->gateway->willReturn('pending');

    $bill = payAirtime($user, $wallet, 200_00);

    expect($bill->status)->toBe(BillStatus::Pending)
        ->and($wallet->fresh()->balance)->toBe(80000)   // reserved up front
        ->and(billSettlementMinor())->toBe(20000);       // parked in settlement

    $this->gateway->markBill($bill->provider_reference, 'completed');

    expect(billService()->reconcile($bill->fresh()))->toBeTrue()
        ->and($bill->fresh()->status)->toBe(BillStatus::Completed)
        ->and(billSettlementMinor())->toBe(0);
});

it('reverses a pending bill that the provider later reports as failed', function () {
    [$user, $wallet] = billPayer(1_000_00);
    $this->gateway->willReturn('pending');

    $bill = payAirtime($user, $wallet, 200_00);
    $this->gateway->markBill($bill->provider_reference, 'failed');

    expect(billService()->reconcile($bill->fresh()))->toBeTrue()
        ->and($bill->fresh()->status)->toBe(BillStatus::Failed)
        ->and($wallet->fresh()->balance)->toBe(100000)
        ->and(billSettlementMinor())->toBe(0);
});

it('resolves an RRR to its biller and outstanding amount', function () {
    $inquiry = billService()->lookupRrr('100000000001');

    expect($inquiry->rrr)->toBe('100000000001')
        ->and($inquiry->billerName)->not->toBe('')
        ->and($inquiry->amount->amount)->toBeGreaterThan(0)
        ->and($inquiry->isPaid)->toBeFalse();
});

it('rejects an unknown RRR', function () {
    billService()->lookupRrr('not-an-rrr');
})->throws(BillProviderException::class);

it('pays a Remita RRR for its looked-up amount', function () {
    [$user, $wallet] = billPayer(100_000_00);
    $this->gateway->registerRrr('100000000002', 'LASG Land Use Charge', 30_000_00);

    $inquiry = billService()->lookupRrr('100000000002');
    $bill = billService()->pay($user, $wallet, BillCategory::Rrr, 'remita', $inquiry->billerName, '100000000002', $inquiry->amount);

    expect($bill->status)->toBe(BillStatus::Completed)
        ->and($bill->amount)->toBe(30_000_00)
        ->and($bill->biller_name)->toBe('LASG Land Use Charge')
        ->and($wallet->fresh()->balance)->toBe(100_000_00 - 30_000_00);
});
