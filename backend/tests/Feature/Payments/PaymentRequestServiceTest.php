<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\AlatpaySignatureVerifier;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Payments\Services\PaymentRequestService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gateway = new FakeAlatpayGateway();
    $this->app->instance(AlatpayGateway::class, $this->gateway);
});

function paymentRequests(): PaymentRequestService
{
    return app(PaymentRequestService::class);
}

/** @return array{0: User, 1: \App\Domain\Wallet\Models\Wallet} */
function requester(): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    return [$user, $wallet];
}

function signedLinkPayload(string $providerRef, int $amount, string $eventId = 'evt_link_1', string $status = 'completed'): array
{
    $payload = json_encode([
        'id' => $eventId,
        'type' => 'transaction.'.$status,
        'data' => [
            'reference' => $providerRef,
            'amount' => $amount,
            'currency' => 'NGN',
            'status' => $status,
            'customer' => ['name' => 'Ada Payer', 'email' => 'ada@example.com'],
        ],
    ]);

    return [$payload, app(AlatpaySignatureVerifier::class)->sign($payload)];
}

it('creates a payment request and returns a link', function () {
    [$user, $wallet] = requester();

    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    expect($request->status)->toBe(PaymentRequestStatus::Pending)
        ->and($request->amount)->toBe(25000)
        ->and($request->provider_reference)->not->toBeNull()
        ->and($request->payment_link_url)->not->toBeEmpty();
});

it('credits the requester wallet when a valid payment webhook arrives', function () {
    [$user, $wallet] = requester();
    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    [$payload, $signature] = signedLinkPayload($request->provider_reference, 25000);
    paymentRequests()->handleWebhook($payload, $signature);

    expect($request->fresh()->status)->toBe(PaymentRequestStatus::Paid)
        ->and($request->fresh()->transaction_id)->not->toBeNull()
        ->and($request->fresh()->payer_email)->toBe('ada@example.com')
        ->and($wallet->fresh()->balance)->toBe(25000);
});

it('processes a duplicate payment webhook only once', function () {
    [$user, $wallet] = requester();
    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    [$payload, $signature] = signedLinkPayload($request->provider_reference, 25000, 'evt_link_dup');
    paymentRequests()->handleWebhook($payload, $signature);
    paymentRequests()->handleWebhook($payload, $signature);

    expect($wallet->fresh()->balance)->toBe(25000);
});

it('rejects a payment webhook with an invalid signature', function () {
    [$user, $wallet] = requester();
    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    [$payload] = signedLinkPayload($request->provider_reference, 25000);
    paymentRequests()->handleWebhook($payload, 'bad-signature');
})->throws(InvalidWebhookSignatureException::class);

it('does not credit when the webhook amount does not match', function () {
    [$user, $wallet] = requester();
    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    [$payload, $signature] = signedLinkPayload($request->provider_reference, 999_00);
    paymentRequests()->handleWebhook($payload, $signature);

    expect($request->fresh()->status)->toBe(PaymentRequestStatus::Pending)
        ->and($wallet->fresh()->balance)->toBe(0);
});

it('cancels a pending request and then ignores a late payment', function () {
    [$user, $wallet] = requester();
    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    paymentRequests()->cancel($request);
    expect($request->fresh()->status)->toBe(PaymentRequestStatus::Cancelled);

    [$payload, $signature] = signedLinkPayload($request->provider_reference, 25000, 'evt_after_cancel');
    paymentRequests()->handleWebhook($payload, $signature);

    expect($wallet->fresh()->balance)->toBe(0);
});

it('reconciles a pending request AlatPay reports as paid', function () {
    [$user, $wallet] = requester();
    $request = paymentRequests()->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    $this->gateway->markPaid($request->provider_reference, 25000);

    expect(paymentRequests()->reconcile($request->fresh()))->toBeTrue()
        ->and($request->fresh()->status)->toBe(PaymentRequestStatus::Paid)
        ->and($wallet->fresh()->balance)->toBe(25000);
});
