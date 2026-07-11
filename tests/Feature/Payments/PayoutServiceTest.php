<?php

declare(strict_types=1);

use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Payments\Contracts\PayoutGateway;
use App\Domain\Payments\Enums\PayoutStatus;
use App\Domain\Payments\Exceptions\PayoutUnavailableException;
use App\Domain\Payments\Paystack\Gateways\FakePaystackPayoutGateway;
use App\Domain\Payments\Paystack\PaystackSignatureVerifier;
use App\Domain\Payments\Services\PayoutService;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'reton.payouts.provider' => 'paystack',
        'reton.features.withdraw' => true,
        'services.paystack.driver' => 'fake',
    ]);

    $this->gateway = new FakePaystackPayoutGateway;
    $this->app->instance(PayoutGateway::class, $this->gateway);
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

function paystackTransferWebhook(string $providerRef, string $merchantRef, string $status, string $eventId): void
{
    $event = match ($status) {
        'completed' => 'transfer.success',
        'failed' => 'transfer.failed',
        default => 'transfer.pending',
    };

    $payload = json_encode([
        'event' => $event,
        'data' => [
            'id' => $eventId,
            'transfer_code' => $providerRef,
            'reference' => $merchantRef,
            'amount' => 40000,
            'status' => $status === 'completed' ? 'success' : $status,
        ],
    ], JSON_THROW_ON_ERROR);

    payouts()->handlePaystackWebhook($payload, app(PaystackSignatureVerifier::class)->sign($payload));
}

it('reserves funds when a payout is requested', function () {
    [$user, $wallet] = payee(1_000_00);

    $payout = request_payout($user, $wallet, 400_00);

    expect($payout->status)->toBe(PayoutStatus::Pending)
        ->and($payout->provider)->toBe('paystack')
        ->and($payout->reservation_transaction_id)->not->toBeNull()
        ->and($payout->provider_reference)->not->toBeNull()
        ->and($wallet->fresh()->balance)->toBe(60000)
        ->and(settlementMinor())->toBe(40000);
});

it('refuses a payout that exceeds the available balance', function () {
    [$user, $wallet] = payee(100_00);

    request_payout($user, $wallet, 500_00);
})->throws(InsufficientFundsException::class);

it('does not reserve funds when the gateway cannot disburse', function () {
    $gateway = Mockery::mock(PayoutGateway::class);
    $gateway->shouldReceive('supportsOutboundTransfers')->andReturn(false);
    $this->app->instance(PayoutGateway::class, $gateway);

    [$user, $wallet] = payee(1_000_00);

    expect(fn () => request_payout($user, $wallet, 400_00))
        ->toThrow(PayoutUnavailableException::class);

    expect($wallet->fresh()->balance)->toBe(100000)
        ->and(settlementMinor())->toBe(0);
});

it('settles the payout when Paystack confirms the transfer', function () {
    [$user, $wallet] = payee(1_000_00);
    $payout = request_payout($user, $wallet, 400_00);

    paystackTransferWebhook($payout->provider_reference, $payout->reference, 'completed', 'evt_done');

    expect($payout->fresh()->status)->toBe(PayoutStatus::Completed)
        ->and($payout->fresh()->settlement_transaction_id)->not->toBeNull()
        ->and($wallet->fresh()->balance)->toBe(60000)
        ->and(settlementMinor())->toBe(0);
});

it('reverses the payout and restores the wallet when the transfer fails', function () {
    [$user, $wallet] = payee(1_000_00);
    $payout = request_payout($user, $wallet, 400_00);

    paystackTransferWebhook($payout->provider_reference, $payout->reference, 'failed', 'evt_fail');

    expect($payout->fresh()->status)->toBe(PayoutStatus::Failed)
        ->and($wallet->fresh()->balance)->toBe(100000)
        ->and(settlementMinor())->toBe(0);
});

it('processes a duplicate payout webhook only once', function () {
    [$user, $wallet] = payee(1_000_00);
    $payout = request_payout($user, $wallet, 400_00);

    paystackTransferWebhook($payout->provider_reference, $payout->reference, 'completed', 'evt_dup');
    paystackTransferWebhook($payout->provider_reference, $payout->reference, 'completed', 'evt_dup');

    expect($wallet->fresh()->balance)->toBe(60000)
        ->and(settlementMinor())->toBe(0);
});

it('reconciles a pending payout that Paystack reports as completed', function () {
    [$user, $wallet] = payee(1_000_00);
    $payout = request_payout($user, $wallet, 400_00);

    $this->gateway->markTransfer($payout->provider_reference, 'completed');

    expect(payouts()->reconcile($payout->fresh()))->toBeTrue()
        ->and($payout->fresh()->status)->toBe(PayoutStatus::Completed);
});
