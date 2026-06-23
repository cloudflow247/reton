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
use App\Domain\Payments\Alatpay\Exceptions\AlatpayException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Live AlatPay integration over HTTP. Endpoint shapes follow AlatPay's
 * collections API; amounts are sent in minor units.
 */
class HttpAlatpayGateway implements AlatpayGateway
{
    public function createCollection(CollectionRequest $request): CollectionResponse
    {
        $response = $this->client()->post('/bank-transfer/api/v1/bankTransfer/virtualAccount', [
            'businessId' => config('services.alatpay.business_id'),
            'amount' => $request->amount->amount,
            'currency' => $request->amount->currency,
            'orderId' => $request->reference,
            'description' => $request->description,
            'customer' => [
                'email' => $request->customerEmail,
                'name' => $request->customerName,
                'phone' => $request->customerPhone,
            ],
        ]);

        if (! $response->successful()) {
            throw AlatpayException::requestFailed('createCollection', $response->status());
        }

        $data = (array) $response->json('data', []);

        return new CollectionResponse(
            providerReference: (string) ($data['transactionId'] ?? $request->reference),
            accountNumber: (string) ($data['virtualBankAccountNumber'] ?? ''),
            bankName: (string) ($data['virtualBankCode'] ?? 'AlatPay'),
            accountName: (string) ($data['virtualBankAccountName'] ?? $request->customerName),
            expiresAt: isset($data['expiredAt']) ? (string) $data['expiredAt'] : null,
        );
    }

    public function createPaymentLink(PaymentLinkRequest $request): PaymentLinkResponse
    {
        $response = $this->client()->post('/payment-link/api/v1/links', [
            'businessId' => config('services.alatpay.business_id'),
            'amount' => $request->amount->amount,
            'currency' => $request->amount->currency,
            'orderId' => $request->reference,
            'title' => $request->title,
            'description' => $request->description,
            'customer' => ['email' => $request->customerEmail],
            'redirectUrl' => $request->redirectUrl,
            'expiresAt' => $request->expiresAt,
        ]);

        if (! $response->successful()) {
            throw AlatpayException::requestFailed('createPaymentLink', $response->status());
        }

        $data = (array) $response->json('data', []);

        $paymentLinkUrl = (string) ($data['url'] ?? $data['paymentLink'] ?? '');

        if ($paymentLinkUrl === '') {
            throw AlatpayException::requestFailed('createPaymentLink', $response->status());
        }

        return new PaymentLinkResponse(
            providerReference: (string) ($data['transactionId'] ?? $data['linkId'] ?? $request->reference),
            paymentLinkUrl: $paymentLinkUrl,
            expiresAt: isset($data['expiredAt']) ? (string) $data['expiredAt'] : $request->expiresAt,
        );
    }

    public function fetchTransaction(string $providerReference): ?RemoteTransaction
    {
        $response = $this->client()->get('/bank-transfer/api/v1/bankTransfer/transactions/'.$providerReference);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw AlatpayException::requestFailed('fetchTransaction', $response->status());
        }

        $data = (array) $response->json('data', []);

        return new RemoteTransaction(
            providerReference: $providerReference,
            status: $this->normaliseStatus((string) ($data['status'] ?? 'pending')),
            amount: (int) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? 'NGN'),
        );
    }

    public function initiateTransfer(TransferRequest $request): TransferResponse
    {
        $response = $this->client()->post('/transfer/api/v1/transfers', [
            'businessId' => config('services.alatpay.business_id'),
            'amount' => $request->amount->amount,
            'currency' => $request->amount->currency,
            'reference' => $request->reference,
            'narration' => $request->narration,
            'beneficiary' => [
                'bankCode' => $request->bankCode,
                'accountNumber' => $request->accountNumber,
                'accountName' => $request->accountName,
            ],
        ]);

        if (! $response->successful()) {
            throw AlatpayException::requestFailed('initiateTransfer', $response->status());
        }

        $data = (array) $response->json('data', []);

        return new TransferResponse(
            providerReference: (string) ($data['transferId'] ?? $request->reference),
            status: $this->normaliseStatus((string) ($data['status'] ?? 'pending')),
        );
    }

    public function fetchTransfer(string $providerReference): ?RemoteTransaction
    {
        $response = $this->client()->get('/transfer/api/v1/transfers/'.$providerReference);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw AlatpayException::requestFailed('fetchTransfer', $response->status());
        }

        $data = (array) $response->json('data', []);

        return new RemoteTransaction(
            providerReference: $providerReference,
            status: $this->normaliseStatus((string) ($data['status'] ?? 'pending')),
            amount: (int) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? 'NGN'),
        );
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.alatpay.base_url'))
            ->timeout((int) config('services.alatpay.timeout', 15))
            ->withHeaders(['Ocp-Apim-Subscription-Key' => (string) config('services.alatpay.api_key')])
            ->acceptJson()
            ->asJson();
    }

    private function normaliseStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'successful', 'success' => 'completed',
            'failed', 'declined' => 'failed',
            default => 'pending',
        };
    }
}
