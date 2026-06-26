<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Gateways;

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\CollectionRequest;
use App\Domain\Payments\Alatpay\Data\CollectionResponse;
use App\Domain\Payments\Alatpay\Data\PaymentLinkRequest;
use App\Domain\Payments\Alatpay\Data\PaymentLinkResponse;
use App\Domain\Payments\Alatpay\Data\RemoteTransaction;
use App\Domain\Payments\Alatpay\Data\StaticAccountProvisionResponse;
use App\Domain\Payments\Alatpay\Data\StaticAccountRequest;
use App\Domain\Payments\Alatpay\Data\StaticAccountResponse;
use App\Domain\Payments\Alatpay\Data\StaticAccountTransaction;
use App\Domain\Payments\Alatpay\Data\StaticAccountVerifyRequest;
use App\Domain\Payments\Alatpay\Data\TransferRequest;
use App\Domain\Payments\Alatpay\Data\TransferResponse;
use App\Domain\Payments\Alatpay\Exceptions\AlatpayException;

/**
 * An in-memory AlatPay gateway for local development and tests. Deterministic;
 * never touches the network.
 */
class FakeAlatpayGateway implements AlatpayGateway
{
    /** @var array<string, array{currency: string, amount: int, status: string}> */
    private array $transactions = [];

    /** @var array<string, array{currency: string, amount: int, status: string}> */
    private array $transfers = [];

    private bool $provisionImmediate = false;

    /** @var array<string, array{accountNumber: ?string, otpTrackingId: ?string}> */
    private array $staticWallets = [];

    /** @var array<string, array<int, StaticAccountTransaction>> keyed by account number */
    private array $staticTransactions = [];

    public function createCollection(CollectionRequest $request): CollectionResponse
    {
        $providerReference = 'ALT-'.$request->reference;

        $this->transactions[$providerReference] = [
            'currency' => $request->amount->currency,
            'amount' => $request->amount->amount,
            'status' => 'pending',
        ];

        return new CollectionResponse(
            providerReference: $providerReference,
            accountNumber: '9'.substr(preg_replace('/\D/', '', $request->reference).'0000000000', 0, 9),
            bankName: 'Wema Bank',
            accountName: 'RETON / '.$request->customerName,
        );
    }

    public function createPaymentLink(PaymentLinkRequest $request): PaymentLinkResponse
    {
        $providerReference = 'ALT-LINK-'.$request->reference;

        $this->transactions[$providerReference] = [
            'currency' => $request->amount->currency,
            'amount' => $request->amount->amount,
            'status' => 'pending',
        ];

        return new PaymentLinkResponse(
            providerReference: $providerReference,
            paymentLinkUrl: 'https://pay.alatpay.test/'.$request->reference,
            expiresAt: $request->expiresAt,
        );
    }

    public function fetchTransaction(string $providerReference): ?RemoteTransaction
    {
        $record = $this->transactions[$providerReference] ?? null;

        if ($record === null) {
            return null;
        }

        return new RemoteTransaction(
            providerReference: $providerReference,
            status: $record['status'],
            amount: $record['amount'],
            currency: $record['currency'],
        );
    }

    /**
     * Test/dev helper: simulate AlatPay confirming a payment.
     */
    public function markPaid(string $providerReference, int $amount, string $currency = 'NGN'): void
    {
        $this->transactions[$providerReference] = [
            'currency' => $currency,
            'amount' => $amount,
            'status' => 'completed',
        ];
    }

    public function initiateTransfer(TransferRequest $request): TransferResponse
    {
        $providerReference = 'ALT-TRF-'.$request->reference;

        $this->transfers[$providerReference] = [
            'currency' => $request->amount->currency,
            'amount' => $request->amount->amount,
            'status' => 'pending',
        ];

        return new TransferResponse($providerReference, 'pending');
    }

    public function fetchTransfer(string $providerReference): ?RemoteTransaction
    {
        $record = $this->transfers[$providerReference] ?? null;

        if ($record === null) {
            return null;
        }

        return new RemoteTransaction(
            providerReference: $providerReference,
            status: $record['status'],
            amount: $record['amount'],
            currency: $record['currency'],
        );
    }

    /**
     * Test/dev helper: simulate AlatPay settling or failing a payout.
     */
    public function markTransfer(string $providerReference, string $status): void
    {
        if (isset($this->transfers[$providerReference])) {
            $this->transfers[$providerReference]['status'] = $status;
        }
    }

    public function provisionReturnsImmediately(bool $immediate): void
    {
        $this->provisionImmediate = $immediate;
    }

    public function provisionStaticAccount(StaticAccountRequest $request): StaticAccountProvisionResponse
    {
        $staticWalletId = 'SW-'.$request->reference;
        $accountNumber = '04'.substr(preg_replace('/\D/', '', $request->reference).'00000000', 0, 8);

        if ($this->provisionImmediate) {
            $this->staticWallets[$staticWalletId] = ['accountNumber' => $accountNumber, 'otpTrackingId' => null];

            return new StaticAccountProvisionResponse($staticWalletId, null, $accountNumber, 'RETON STATIC');
        }

        $this->staticWallets[$staticWalletId] = ['accountNumber' => $accountNumber, 'otpTrackingId' => 'OTP-'.$request->reference];

        return new StaticAccountProvisionResponse($staticWalletId, 'OTP-'.$request->reference, null, null);
    }

    public function verifyStaticAccount(StaticAccountVerifyRequest $request): StaticAccountResponse
    {
        if ($request->otp !== '123456') {
            throw AlatpayException::requestFailed('verifyStaticAccount', 400);
        }

        $wallet = $this->staticWallets[$request->staticWalletId] ?? null;

        if ($wallet === null || $wallet['accountNumber'] === null) {
            throw AlatpayException::requestFailed('verifyStaticAccount', 404);
        }

        return new StaticAccountResponse(
            providerReference: $request->staticWalletId,
            accountNumber: $wallet['accountNumber'],
            accountName: 'RETON STATIC',
        );
    }

    /**
     * Test/dev helper: inject a static-account transaction with any status.
     */
    public function recordStaticTransaction(string $accountNumber, int $status, float $amountMajor, string $transactionId): void
    {
        $this->staticTransactions[$accountNumber][] = new StaticAccountTransaction(
            transactionId: $transactionId,
            status: $status,
            accountNumber: $accountNumber,
            amountMajor: $amountMajor,
            narration: 'ALAT TRANSFER',
            notificationEmail: null,
        );
    }

    /**
     * Test/dev helper: simulate a successful inbound payment (status = 1).
     */
    public function markStaticFunded(string $accountNumber, float $amountMajor, string $transactionId): void
    {
        $this->recordStaticTransaction($accountNumber, 1, $amountMajor, $transactionId);
    }

    public function fetchStaticAccountTransactions(string $accountNumber, int $page = 1, int $limit = 50): array
    {
        return $this->staticTransactions[$accountNumber] ?? [];
    }
}
