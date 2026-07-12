<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\AlatpaySignatureVerifier;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Contracts\PayoutGateway;
use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Enums\PayoutStatus;
use App\Domain\Payments\Gateways\AlatpayPayoutGateway;
use App\Domain\Payments\Services\AlatpayDepositService;
use App\Domain\Payments\Services\AlatpayWebhookRouter;
use App\Domain\Payments\Services\PaymentRequestService;
use App\Domain\Payments\Services\PayoutService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway);
});

function router(): AlatpayWebhookRouter
{
    return app(AlatpayWebhookRouter::class);
}

function signedCollection(string $providerRef, int $amount, string $eventId): array
{
    $payload = json_encode([
        'id' => $eventId,
        'type' => 'transaction.completed',
        'data' => ['reference' => $providerRef, 'amount' => $amount, 'currency' => 'NGN', 'status' => 'completed'],
    ]);

    return [$payload, app(AlatpaySignatureVerifier::class)->sign($payload)];
}

it('routes a payment-request collection to the request handler', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $request = app(PaymentRequestService::class)->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch');

    [$payload, $signature] = signedCollection($request->provider_reference, 25000, 'evt_router_req');
    router()->handle($payload, $signature);

    expect($request->fresh()->status)->toBe(PaymentRequestStatus::Paid)
        ->and($wallet->fresh()->balance)->toBe(25000);
});

it('routes a deposit collection to the deposit handler', function () {
    $user = User::factory()->create();
    ensureVerifiedBvn($user);
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $deposit = app(AlatpayDepositService::class)->initiate($user, $wallet, Money::of(500_00, 'NGN'));

    [$payload, $signature] = signedCollection($deposit->provider_reference, 50000, 'evt_router_dep');
    router()->handle($payload, $signature);

    expect($wallet->fresh()->balance)->toBe(50000);
});

/**
 * Build a signed transfer.* webhook payload for the router.
 * Named distinctly to avoid collision with transferWebhook() in PayoutServiceTest.php
 * (which calls payouts()->handleWebhook() directly, not router()->handle()).
 *
 * @return array{0: string, 1: string}
 */
function signedTransferWebhookPayload(string $providerRef, string $status, string $eventId): array
{
    $payload = json_encode([
        'id' => $eventId,
        'type' => 'transfer.'.$status,
        'data' => ['reference' => $providerRef, 'amount' => 0, 'status' => $status],
    ]);

    return [$payload, app(AlatpaySignatureVerifier::class)->sign($payload)];
}

it('routes a transfer.* event to the payout handler and settles the payout', function () {
    config(['reton.payouts.provider' => 'alatpay']);
    $this->app->instance(
        PayoutGateway::class,
        new AlatpayPayoutGateway(app(AlatpayGateway::class)),
    );

    // Inline setup: funded wallet + payout request, mirroring PayoutServiceTest helpers.
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    app(WalletService::class)->fund($wallet, Money::of(1_000_00, 'NGN'));
    $wallet = $wallet->refresh();

    $payout = app(PayoutService::class)->request(
        $user,
        $wallet,
        Money::of(400_00, 'NGN'),
        '044',
        '0123456789',
        'Ada Lovelace',
    );

    expect($payout->provider)->toBe('alatpay')
        ->and($payout->status)->toBe(PayoutStatus::Pending);

    [$payload, $signature] = signedTransferWebhookPayload(
        $payout->provider_reference,
        'completed',
        'evt_router_transfer_1',
    );
    router()->handle($payload, $signature);

    expect($payout->fresh()->status)->toBe(PayoutStatus::Completed);
});

it('replaying the same collection webhook credits the requester only once', function () {
    // Inline setup: user + wallet, mirroring the PaymentRequestService requester() helper.
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    $request = app(PaymentRequestService::class)->create($user, $wallet, Money::of(250_00, 'NGN'), 'Dedup test');

    [$payload, $signature] = signedCollection($request->provider_reference, 25000, 'evt_router_dedup_1');

    router()->handle($payload, $signature);
    router()->handle($payload, $signature);  // identical replay — must be a no-op

    expect($wallet->fresh()->balance)->toBe(25000)     // credited exactly once
        ->and($request->fresh()->status)->toBe(PaymentRequestStatus::Paid);
});
