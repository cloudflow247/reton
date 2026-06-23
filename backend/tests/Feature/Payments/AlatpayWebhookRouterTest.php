<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\AlatpaySignatureVerifier;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Services\AlatpayDepositService;
use App\Domain\Payments\Services\AlatpayWebhookRouter;
use App\Domain\Payments\Services\PaymentRequestService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway());
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
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $deposit = app(AlatpayDepositService::class)->initiate($user, $wallet, Money::of(500_00, 'NGN'));

    [$payload, $signature] = signedCollection($deposit->provider_reference, 50000, 'evt_router_dep');
    router()->handle($payload, $signature);

    expect($wallet->fresh()->balance)->toBe(50000);
});
