<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Data\PaymentLinkRequest;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Support\Money\Money;

it('creates a deterministic payment link and tracks it as a pending transaction', function () {
    $gateway = new FakeAlatpayGateway;

    $response = $gateway->createPaymentLink(new PaymentLinkRequest(
        reference: 'REQ-ABC',
        amount: Money::of(250_00, 'NGN'),
        title: 'Lunch money',
        customerEmail: 'requester@example.com',
    ));

    expect($response->providerReference)->toBe('ALT-LINK-REQ-ABC')
        ->and($response->paymentLinkUrl)->toBe('https://pay.alatpay.test/REQ-ABC');

    // The link is reconcilable via the same transaction lookup deposits use.
    $gateway->markPaid($response->providerReference, 250_00);
    $remote = $gateway->fetchTransaction($response->providerReference);

    expect($remote)->not->toBeNull()
        ->and($remote->isSuccessful())->toBeTrue()
        ->and($remote->amount)->toBe(25000);
});
