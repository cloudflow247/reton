<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\AlatpaySignatureVerifier;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\DepositStatus;
use App\Domain\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Services\AlatpayDepositService;
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

function deposits(): AlatpayDepositService
{
    return app(AlatpayDepositService::class);
}

/**
 * @return array{0: User, 1: Wallet}
 */
function depositor(): array
{
    $user = User::factory()->create();
    ensureVerifiedBvn($user);
    $wallet = app(WalletService::class)->open($user, 'NGN');

    return [$user, $wallet];
}

function signedPayload(string $providerRef, int $amount, string $eventId = 'evt_1', string $status = 'completed'): array
{
    $payload = json_encode([
        'id' => $eventId,
        'type' => 'transaction.'.$status,
        'data' => ['reference' => $providerRef, 'amount' => $amount, 'currency' => 'NGN', 'status' => $status],
    ]);

    return [$payload, app(AlatpaySignatureVerifier::class)->sign($payload)];
}

it('initiates a deposit and returns a virtual account', function () {
    [$user, $wallet] = depositor();

    $deposit = deposits()->initiate($user, $wallet, Money::of(500_00, 'NGN'));

    expect($deposit->status)->toBe(DepositStatus::Pending)
        ->and($deposit->amount)->toBe(50000)
        ->and($deposit->provider_reference)->not->toBeNull()
        ->and($deposit->virtual_account['account_number'])->not->toBeEmpty()
        ->and($deposit->metadata['method'])->toBe('bank_transfer');
});

it('initiates an alatpay checkout deposit with a payment link', function () {
    [$user, $wallet] = depositor();

    $deposit = deposits()->initiate(
        $user,
        $wallet,
        Money::of(500_00, 'NGN'),
        \App\Domain\Payments\Enums\DepositMethod::AlatpayCheckout,
    );

    expect($deposit->metadata['method'])->toBe('alatpay_checkout')
        ->and($deposit->metadata['payment_link_url'])->toContain('pay.alatpay.test')
        ->and($deposit->virtual_account)->toBeNull();
});

it('initiates a card-only deposit with channel 1', function () {
    [$user, $wallet] = depositor();

    $deposit = deposits()->initiate(
        $user,
        $wallet,
        Money::of(500_00, 'NGN'),
        \App\Domain\Payments\Enums\DepositMethod::AlatpayCard,
    );

    expect($deposit->metadata['method'])->toBe('alatpay_card')
        ->and($deposit->metadata['payment_link_url'])->toContain('channel=1');
});

it('matches deposits by business reference on webhook', function () {
    [$user, $wallet] = depositor();
    $deposit = deposits()->initiate(
        $user,
        $wallet,
        Money::of(500_00, 'NGN'),
        \App\Domain\Payments\Enums\DepositMethod::AlatpayCheckout,
    );

    [$payload, $signature] = signedPayload($deposit->reference, 50000);
    deposits()->handleWebhook($payload, $signature);

    expect($deposit->fresh()->status)->toBe(DepositStatus::Completed)
        ->and($wallet->fresh()->balance)->toBe(50000);
});

it('credits the wallet when a valid completion webhook arrives', function () {
    [$user, $wallet] = depositor();
    $deposit = deposits()->initiate($user, $wallet, Money::of(500_00, 'NGN'));

    [$payload, $signature] = signedPayload($deposit->provider_reference, 50000);
    deposits()->handleWebhook($payload, $signature);

    expect($deposit->fresh()->status)->toBe(DepositStatus::Completed)
        ->and($deposit->fresh()->transaction_id)->not->toBeNull()
        ->and($wallet->fresh()->balance)->toBe(50000);
});

it('processes a duplicate webhook only once', function () {
    [$user, $wallet] = depositor();
    $deposit = deposits()->initiate($user, $wallet, Money::of(500_00, 'NGN'));

    [$payload, $signature] = signedPayload($deposit->provider_reference, 50000, 'evt_dup');
    deposits()->handleWebhook($payload, $signature);
    deposits()->handleWebhook($payload, $signature);

    expect($wallet->fresh()->balance)->toBe(50000); // credited once, not twice
});

it('rejects a webhook with an invalid signature', function () {
    [$user, $wallet] = depositor();
    $deposit = deposits()->initiate($user, $wallet, Money::of(500_00, 'NGN'));

    [$payload] = signedPayload($deposit->provider_reference, 50000);
    deposits()->handleWebhook($payload, 'not-the-real-signature');
})->throws(InvalidWebhookSignatureException::class);

it('ignores a webhook for an unknown reference', function () {
    depositor();

    [$payload, $signature] = signedPayload('ALT-UNKNOWN', 50000);
    $event = deposits()->handleWebhook($payload, $signature);

    expect($event->status)->toBe('ignored')
        ->and(Deposit::where('status', DepositStatus::Completed->value)->count())->toBe(0);
});

it('does not credit when the webhook amount does not match the deposit', function () {
    [$user, $wallet] = depositor();
    $deposit = deposits()->initiate($user, $wallet, Money::of(500_00, 'NGN'));

    [$payload, $signature] = signedPayload($deposit->provider_reference, 999_00); // wrong amount
    deposits()->handleWebhook($payload, $signature);

    expect($deposit->fresh()->status)->toBe(DepositStatus::Pending)
        ->and($wallet->fresh()->balance)->toBe(0);
});

it('reconciles a pending deposit that AlatPay reports as paid', function () {
    [$user, $wallet] = depositor();
    $deposit = deposits()->initiate($user, $wallet, Money::of(500_00, 'NGN'));

    // Simulate AlatPay having received the funds but the webhook never arriving.
    $this->gateway->markPaid($deposit->provider_reference, 50000);

    $reconciled = deposits()->reconcile($deposit->fresh());

    expect($reconciled)->toBeTrue()
        ->and($deposit->fresh()->status)->toBe(DepositStatus::Completed)
        ->and($wallet->fresh()->balance)->toBe(50000);
});
