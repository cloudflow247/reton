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
            'customer' => array_filter([
                'email' => $request->customerEmail,
                'name' => $request->customerName,
                'phone' => $request->customerPhone,
                'bvn' => $request->customerBvn,
            ]),
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
        $payload = [
            'businessId' => config('services.alatpay.business_id'),
            'amount' => $request->amount->amount,
            'currency' => $request->amount->currency,
            'orderId' => $request->reference,
            'title' => $request->title,
            'description' => $request->description,
            'customer' => array_filter([
                'email' => $request->customerEmail,
                'name' => $request->customerName ?: null,
                'phone' => $request->customerPhone,
                'bvn' => $request->customerBvn,
            ]),
            'redirectUrl' => $request->redirectUrl,
            'expiresAt' => $request->expiresAt,
        ];

        if ($request->channel !== null) {
            $payload['channel'] = $request->channel;
        }

        $response = $this->client()->post('/payment-link/api/v1/links', $payload);

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

    public function provisionStaticAccount(StaticAccountRequest $request): StaticAccountProvisionResponse
    {
        $response = $this->client()->post('/alatpay-wallet/api/v1/staticaccount', [
            'businessId' => config('services.alatpay.business_id'),
            'staticWalletType' => $request->walletType,
            'bvn' => $request->bvn,
            'email' => $request->email,
        ]);

        if (! $response->successful()) {
            throw AlatpayException::requestFailed('provisionStaticAccount', $response->status());
        }

        $data = (array) $response->json('data', $response->json());

        $staticWalletId = (string) ($data['id'] ?? '');

        if ($staticWalletId === '') {
            throw AlatpayException::requestFailed('provisionStaticAccount', $response->status());
        }

        return new StaticAccountProvisionResponse(
            staticWalletId: $staticWalletId,
            otpTrackingId: isset($data['otpTrackingId']) ? (string) $data['otpTrackingId'] : null,
            accountNumber: isset($data['accountNumber']) ? (string) $data['accountNumber'] : null,
            accountName: isset($data['accountName']) ? (string) $data['accountName'] : null,
        );
    }

    public function verifyStaticAccount(StaticAccountVerifyRequest $request): StaticAccountResponse
    {
        $response = $this->client()->post('/alatpay-wallet/api/v1/staticaccount/validateAndCreate', [
            'staticWalletId' => $request->staticWalletId,
            'businessId' => config('services.alatpay.business_id'),
            'otp' => $request->otp,
            'trackingId' => $request->trackingId,
        ]);

        if (! $response->successful()) {
            throw AlatpayException::requestFailed('verifyStaticAccount', $response->status());
        }

        $data = (array) $response->json('data', $response->json());

        $accountNumber = (string) ($data['accountNumber'] ?? '');

        if ($accountNumber === '') {
            throw AlatpayException::requestFailed('verifyStaticAccount', $response->status());
        }

        return new StaticAccountResponse(
            providerReference: (string) ($data['id'] ?? $request->staticWalletId),
            accountNumber: $accountNumber,
            accountName: isset($data['accountName']) ? (string) $data['accountName'] : null,
        );
    }

    public function fetchStaticAccountTransactions(string $accountNumber, int $page = 1, int $limit = 50): array
    {
        $response = $this->client()->get('/alatpay-wallet/api/v1/staticaccount/transactions', [
            'businessId' => config('services.alatpay.business_id'),
            'accountNumber' => $accountNumber,
            'pageNumber' => $page,
            'limit' => $limit,
        ]);

        if (! $response->successful()) {
            throw AlatpayException::requestFailed('fetchStaticAccountTransactions', $response->status());
        }

        $rows = (array) $response->json('staticAccountTransactionResponses', $response->json('data.staticAccountTransactionResponses', []));

        return array_map(static fn (array $row): StaticAccountTransaction => new StaticAccountTransaction(
            transactionId: (string) ($row['staticAccountTransactionId'] ?? ''),
            status: (int) ($row['status'] ?? 0),
            accountNumber: (string) ($row['accountNumber'] ?? $accountNumber),
            amountMajor: (float) ($row['amount'] ?? 0),
            narration: isset($row['narration']) ? (string) $row['narration'] : null,
            notificationEmail: isset($row['notificationEmail']) ? (string) $row['notificationEmail'] : null,
        ), $rows);
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
