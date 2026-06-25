<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Data\StaticAccountRequest;
use App\Domain\Payments\Alatpay\Data\StaticAccountVerifyRequest;
use App\Domain\Payments\Alatpay\Exceptions\AlatpayException;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;

it('provisions with an OTP step then verifies into a live account number', function () {
    $gateway = new FakeAlatpayGateway();

    $provision = $gateway->provisionStaticAccount(new StaticAccountRequest(
        walletType: 1,
        bvn: '12345678901',
        email: 'user@example.com',
        reference: 'SA-ABC',
    ));

    expect($provision->staticWalletId)->not->toBeEmpty()
        ->and($provision->otpTrackingId)->not->toBeNull()
        ->and($provision->accountNumber)->toBeNull();

    $verified = $gateway->verifyStaticAccount(new StaticAccountVerifyRequest(
        staticWalletId: $provision->staticWalletId,
        otp: '123456',
        trackingId: (string) $provision->otpTrackingId,
    ));

    expect($verified->accountNumber)->not->toBeEmpty()
        ->and($verified->providerReference)->toBe($provision->staticWalletId);
});

it('rejects a wrong OTP', function () {
    $gateway = new FakeAlatpayGateway();
    $provision = $gateway->provisionStaticAccount(new StaticAccountRequest(1, '12345678901', null, 'SA-X'));

    $gateway->verifyStaticAccount(new StaticAccountVerifyRequest($provision->staticWalletId, '000000', (string) $provision->otpTrackingId));
})->throws(AlatpayException::class);

it('can provision a collection wallet that returns an account number immediately', function () {
    $gateway = new FakeAlatpayGateway();
    $gateway->provisionReturnsImmediately(true);

    $provision = $gateway->provisionStaticAccount(new StaticAccountRequest(2, '12345678901', null, 'SA-COL'));

    expect($provision->otpTrackingId)->toBeNull()
        ->and($provision->accountNumber)->not->toBeNull();
});

it('reports recorded transactions in major units with a minor-unit helper', function () {
    $gateway = new FakeAlatpayGateway();
    $provision = $gateway->provisionStaticAccount(new StaticAccountRequest(1, '12345678901', null, 'SA-T'));
    $verified = $gateway->verifyStaticAccount(new StaticAccountVerifyRequest($provision->staticWalletId, '123456', (string) $provision->otpTrackingId));

    $gateway->markStaticFunded($verified->accountNumber, 100.00, 'txn-1');
    $txns = $gateway->fetchStaticAccountTransactions($verified->accountNumber);

    expect($txns)->toHaveCount(1)
        ->and($txns[0]->isSuccessful())->toBeTrue()
        ->and($txns[0]->amountMajor)->toBe(100.00)
        ->and($txns[0]->amountMinor())->toBe(10000)
        ->and($txns[0]->transactionId)->toBe('txn-1');
});
