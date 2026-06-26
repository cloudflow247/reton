<?php

declare(strict_types=1);

use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Payments\Alatpay\AlatpaySignatureVerifier;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\PayoutStatus;
use App\Domain\Payments\Services\PayoutService;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gateway = new FakeAlatpayGateway;
    $this->app->instance(AlatpayGateway::class, $this->gateway);
});

function payouts(): PayoutService
{
    return app(PayoutService::class);
}

/**
 * @return array{0: User, 1: Wallet}
 */
function payee(int $fund = 1_000_00): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    if ($fund > 0) {
        app(WalletService::class)->fund($wallet, Money::of($fund, 'NGN'));
    }

    return [$user, $wallet->refresh()];
}

function settlementMinor(): int
{
    return app(SystemAccountResolver::class)->resolve(SystemAccount::SettlementPayable, 'NGN')->balanceMinor();
}

function request_payout($user, $wallet, int $amount)
{
    return payouts()->request($user, $wallet, Money::of($amount, 'NGN'), '044', '0123456789', 'Ada Lovelace');
}

function transferWebhook(string $providerRef, string $status, string $eventId): void
{
    $payload = json_encode([
        'id' => $eventId,
        'type' => 'transfer.'.$status,
        'data' => ['reference' => $providerRef, 'amount' => 0, 'status' => $status],
    ]);
    payouts()->handleWebhook($payload, app(AlatpaySignatureVerifier::class)->sign($payload));
}

it('reserves funds when a payout is requested', function () {
    [$user, $wallet] = payee(1_000_00);

    $payout = request_payout($user, $wallet, 400_00);

    expect($payout->status)->toBe(PayoutStatus::Pending)
        ->and($payout->reservation_transaction_id)->not->toBeNull()
        ->and($payout->provider_reference)->not->toBeNull()
        ->and($wallet->fresh()->balance)->toBe(60000)   // debited up front
        ->and(settlementMinor())->toBe(40000);          // parked in settlement
});

it('refuses a payout that exceeds the available balance', function () {
    [$user, $wallet] = payee(100_00);

    request_payout($user, $wallet, 500_00);
})->throws(InsufficientFundsException::class);

it('settles the payout when AlatPay confirms the transfer', function () {
    [$user, $wallet] = payee(1_000_00);
    $payout = request_payout($user, $wallet, 400_00);

    transferWebhook($payout->provider_reference, 'completed', 'evt_done');

    expect($payout->fresh()->status)->toBe(PayoutStatus::Completed)
        ->and($payout->fresh()->settlement_transaction_id)->not->toBeNull()
        ->and($wallet->fresh()->balance)->toBe(60000)   // money has left the wallet
        ->and(settlementMinor())->toBe(0);              // settlement cleared
});

it('reverses the payout and restores the wallet when the transfer fails', function () {
    [$user, $wallet] = payee(1_000_00);
    $payout = request_payout($user, $wallet, 400_00);

    transferWebhook($payout->provider_reference, 'failed', 'evt_fail');

    expect($payout->fresh()->status)->toBe(PayoutStatus::Failed)
        ->and($wallet->fresh()->balance)->toBe(100000)  // funds returned
        ->and(settlementMinor())->toBe(0);
});

it('processes a duplicate payout webhook only once', function () {
    [$user, $wallet] = payee(1_000_00);
    $payout = request_payout($user, $wallet, 400_00);

    transferWebhook($payout->provider_reference, 'completed', 'evt_dup');
    transferWebhook($payout->provider_reference, 'completed', 'evt_dup');

    expect($wallet->fresh()->balance)->toBe(60000)
        ->and(settlementMinor())->toBe(0);
});

it('reconciles a pending payout that AlatPay reports as completed', function () {
    [$user, $wallet] = payee(1_000_00);
    $payout = request_payout($user, $wallet, 400_00);

    $this->gateway->markTransfer($payout->provider_reference, 'completed');

    expect(payouts()->reconcile($payout->fresh()))->toBeTrue()
        ->and($payout->fresh()->status)->toBe(PayoutStatus::Completed);
});
