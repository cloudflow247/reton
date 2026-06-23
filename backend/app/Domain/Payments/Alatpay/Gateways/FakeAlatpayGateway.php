<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Gateways;

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\CollectionRequest;
use App\Domain\Payments\Alatpay\Data\CollectionResponse;
use App\Domain\Payments\Alatpay\Data\PaymentLinkRequest;
use App\Domain\Payments\Alatpay\Data\PaymentLinkResponse;
use App\Domain\Payments\Alatpay\Data\RemoteTransaction;
use App\Domain\Payments\Alatpay\Data\TransferRequest;
use App\Domain\Payments\Alatpay\Data\TransferResponse;

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
}
