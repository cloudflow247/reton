<?php

declare(strict_types=1);

namespace App\Domain\Payments\Paystack\Gateways;

use App\Domain\Payments\Alatpay\Data\RemoteTransaction;
use App\Domain\Payments\Alatpay\Data\TransferRequest;
use App\Domain\Payments\Alatpay\Data\TransferResponse;
use App\Domain\Payments\Contracts\PayoutGateway;

/**
 * In-memory Paystack Transfers gateway for local/tests.
 */
final class FakePaystackPayoutGateway implements PayoutGateway
{
    /** @var array<string, array{status: string, amount: int, currency: string, reference: string}> */
    private array $transfers = [];

    public function supportsOutboundTransfers(): bool
    {
        return true;
    }

    public function initiateTransfer(TransferRequest $request): TransferResponse
    {
        $providerReference = 'PSK-TRF-'.$request->reference;

        $this->transfers[$providerReference] = [
            'status' => 'pending',
            'amount' => $request->amount->amount,
            'currency' => $request->amount->currency,
            'reference' => $request->reference,
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

    public function ping(): void
    {
        // Always healthy in fake mode.
    }

    public function markTransfer(string $providerReference, string $status): void
    {
        if (! isset($this->transfers[$providerReference])) {
            return;
        }

        $this->transfers[$providerReference]['status'] = $status;
    }

    public function referenceFor(string $providerReference): ?string
    {
        return $this->transfers[$providerReference]['reference'] ?? null;
    }
}
