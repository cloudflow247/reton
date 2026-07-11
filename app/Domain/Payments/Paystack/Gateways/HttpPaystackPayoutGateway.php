<?php

declare(strict_types=1);

namespace App\Domain\Payments\Paystack\Gateways;

use App\Domain\Payments\Alatpay\Data\RemoteTransaction;
use App\Domain\Payments\Alatpay\Data\TransferRequest;
use App\Domain\Payments\Alatpay\Data\TransferResponse;
use App\Domain\Payments\Paystack\Exceptions\PaystackException;
use App\Domain\Payments\Contracts\PayoutGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Live Paystack Transfers (disbursements) against api.paystack.co.
 *
 * @see https://paystack.com/docs/transfers/single-transfers/
 */
final class HttpPaystackPayoutGateway implements PayoutGateway
{
    public function supportsOutboundTransfers(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    public function initiateTransfer(TransferRequest $request): TransferResponse
    {
        if (! $this->supportsOutboundTransfers()) {
            throw PaystackException::requestFailed('initiateTransfer', 503);
        }

        $recipient = $this->client()->post('/transferrecipient', [
            'type' => 'nuban',
            'name' => $request->accountName,
            'account_number' => $request->accountNumber,
            'bank_code' => $request->bankCode,
            'currency' => $request->amount->currency,
        ]);

        if (! $recipient->successful() || ! ($recipient->json('status') ?? false)) {
            $message = (string) ($recipient->json('message') ?? 'Could not create transfer recipient');
            throw new PaystackException($message, $recipient->status() ?: 422);
        }

        $recipientCode = (string) ($recipient->json('data.recipient_code') ?? '');

        if ($recipientCode === '') {
            throw PaystackException::requestFailed('createTransferRecipient', $recipient->status());
        }

        $transfer = $this->client()->post('/transfer', [
            'source' => 'balance',
            'amount' => $request->amount->amount,
            'recipient' => $recipientCode,
            'reason' => $request->narration,
            'reference' => $request->reference,
            'currency' => $request->amount->currency,
        ]);

        if (! $transfer->successful() || ! ($transfer->json('status') ?? false)) {
            $message = (string) ($transfer->json('message') ?? 'Transfer initiation failed');
            throw new PaystackException($message, $transfer->status() ?: 422);
        }

        $code = (string) ($transfer->json('data.transfer_code') ?? $transfer->json('data.reference') ?? '');
        $status = $this->normaliseStatus((string) ($transfer->json('data.status') ?? 'pending'));

        if ($code === '') {
            throw PaystackException::requestFailed('initiateTransfer', $transfer->status());
        }

        return new TransferResponse($code, $status);
    }

    public function fetchTransfer(string $providerReference): ?RemoteTransaction
    {
        $response = $this->client()->get('/transfer/'.$providerReference);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw PaystackException::requestFailed('fetchTransfer', $response->status());
        }

        $data = (array) $response->json('data', []);

        return new RemoteTransaction(
            providerReference: (string) ($data['transfer_code'] ?? $providerReference),
            status: $this->normaliseStatus((string) ($data['status'] ?? 'pending')),
            amount: (int) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? 'NGN'),
            narration: isset($data['reason']) ? (string) $data['reason'] : null,
            payerName: null,
            bankName: null,
            channel: 'paystack_transfer',
            paidAt: isset($data['transferred_at']) ? (string) $data['transferred_at'] : null,
        );
    }

    public function ping(): void
    {
        $response = $this->client()->get('/bank', [
            'country' => 'nigeria',
            'perPage' => 1,
        ]);

        if (! $response->successful() || ! ($response->json('status') ?? false)) {
            throw new PaystackException(
                (string) ($response->json('message') ?? 'Paystack API unreachable'),
                $response->status() ?: 502,
            );
        }
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/'))
            ->timeout((int) config('services.paystack.timeout', 15))
            ->acceptJson()
            ->asJson()
            ->withToken((string) config('services.paystack.secret_key'));
    }

    private function normaliseStatus(string $status): string
    {
        return match (strtolower($status)) {
            'success', 'successful', 'completed' => 'completed',
            'failed', 'reversed', 'abandoned', 'blocked', 'rejected' => 'failed',
            default => 'pending',
        };
    }
}
