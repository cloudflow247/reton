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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $this->assertConfigured('provisionStaticAccount');

        try {
            $response = $this->client()->post('/alatpay-wallet/api/v1/staticaccount', [
                'businessId' => config('services.alatpay.business_id'),
                'staticWalletType' => $request->walletType,
                'bvn' => $request->bvn,
                'email' => $request->email,
            ]);
        } catch (ConnectionException|RequestException $e) {
            throw AlatpayException::requestFailed(
                'provisionStaticAccount',
                503,
                'Could not reach ALATPay. Check Base URL (https://apibox.alatpay.ng) and try again.',
            );
        }

        $payload = $response->json();

        if (! $response->successful()) {
            Log::warning('ALATPay provisionStaticAccount failed', [
                'status' => $response->status(),
                'base_url' => config('services.alatpay.base_url'),
                'body' => $payload ?? $response->body(),
            ]);

            throw AlatpayException::requestFailed(
                'provisionStaticAccount',
                $response->status(),
                $this->extractErrorMessage($payload),
            );
        }

        $data = $this->unwrapPayload($payload);
        $root = is_array($payload) ? $payload : [];

        if ($this->looksLikeSoftFailure($root, $data)) {
            throw AlatpayException::requestFailed(
                'provisionStaticAccount',
                400,
                $this->extractErrorMessage($payload) ?? 'ALATPay rejected the BVN request.',
            );
        }

        $staticWalletId = (string) ($data['id'] ?? $data['staticWalletId'] ?? '');

        if ($staticWalletId === '') {
            Log::warning('ALATPay provisionStaticAccount missing wallet id', [
                'base_url' => config('services.alatpay.base_url'),
                'body' => $payload,
            ]);

            throw AlatpayException::requestFailed(
                'provisionStaticAccount',
                $response->status(),
                $this->extractErrorMessage($payload) ?? 'ALATPay returned an empty wallet id. Confirm Business ID and Base URL (apibox.alatpay.ng).',
            );
        }

        $otpTrackingId = isset($data['otpTrackingID'])
            ? (string) $data['otpTrackingID']
            : (isset($data['otpTrackingId']) ? (string) $data['otpTrackingId'] : null);

        $message = (string) ($data['message'] ?? $root['message'] ?? '');

        return new StaticAccountProvisionResponse(
            staticWalletId: $staticWalletId,
            otpTrackingId: $otpTrackingId !== '' ? $otpTrackingId : null,
            accountNumber: isset($data['accountNumber']) ? (string) $data['accountNumber'] : null,
            accountName: isset($data['accountName']) ? (string) $data['accountName'] : null,
            otpHint: $message !== '' ? $message : null,
        );
    }

    public function verifyStaticAccount(StaticAccountVerifyRequest $request): StaticAccountResponse
    {
        $this->assertConfigured('verifyStaticAccount');

        try {
            $response = $this->client()->post('/alatpay-wallet/api/v1/staticaccount/validateAndCreate', [
                'staticWalletId' => $request->staticWalletId,
                'businessId' => config('services.alatpay.business_id'),
                'otp' => $request->otp,
                'trackingId' => $request->trackingId,
            ]);
        } catch (ConnectionException|RequestException $e) {
            throw AlatpayException::requestFailed(
                'verifyStaticAccount',
                503,
                'Could not reach ALATPay. Check Base URL (https://apibox.alatpay.ng) and try again.',
            );
        }

        $payload = $response->json();

        if (! $response->successful()) {
            Log::warning('ALATPay verifyStaticAccount failed', [
                'status' => $response->status(),
                'body' => $payload ?? $response->body(),
            ]);

            throw AlatpayException::requestFailed(
                'verifyStaticAccount',
                $response->status(),
                $this->extractErrorMessage($payload),
            );
        }

        $data = $this->unwrapPayload($payload);

        $accountNumber = (string) ($data['accountNumber'] ?? '');

        if ($accountNumber === '') {
            throw AlatpayException::requestFailed(
                'verifyStaticAccount',
                $response->status(),
                $this->extractErrorMessage($payload) ?? 'ALATPay did not return an account number.',
            );
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
        return Http::baseUrl($this->resolvedBaseUrl())
            ->timeout((int) config('services.alatpay.timeout', 12))
            ->connectTimeout(4)
            ->withHeaders(['Ocp-Apim-Subscription-Key' => (string) config('services.alatpay.api_key')])
            ->acceptJson()
            ->asJson();
    }

    /**
     * Official ALATPay host is apibox. Older Reton defaults used api.alatpay.ng, which breaks static wallet.
     *
     * @see https://docs.alatpay.ng/get-started
     */
    private function resolvedBaseUrl(): string
    {
        $base = rtrim((string) config('services.alatpay.base_url'), '/');

        if ($base === 'https://api.alatpay.ng' || $base === 'http://api.alatpay.ng') {
            Log::warning('ALATPay base_url remapped from api.alatpay.ng to apibox.alatpay.ng');

            return 'https://apibox.alatpay.ng';
        }

        return $base !== '' ? $base : 'https://apibox.alatpay.ng';
    }

    /**
     * Official docs return a flat object; some environments nest under `data`.
     * When `data` is null/empty, fall back to the root payload.
     *
     * @return array<string, mixed>
     */
    private function unwrapPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $nested = $payload['data'] ?? null;

        if (is_array($nested) && $nested !== []) {
            return $nested;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $root
     * @param  array<string, mixed>  $data
     */
    private function looksLikeSoftFailure(array $root, array $data): bool
    {
        foreach ([$root, $data] as $bag) {
            if (array_key_exists('status', $bag) && $bag['status'] === false) {
                return true;
            }
            if (array_key_exists('succeeded', $bag) && $bag['succeeded'] === false) {
                return true;
            }
            if (isset($bag['statusCode']) && (int) $bag['statusCode'] >= 400) {
                return true;
            }
        }

        return false;
    }

    private function assertConfigured(string $operation): void
    {
        if (blank(config('services.alatpay.api_key')) || blank(config('services.alatpay.business_id'))) {
            throw AlatpayException::requestFailed(
                $operation,
                503,
                'ALATPay API key or Business ID is missing. Add them in Admin → Integrations.',
            );
        }
    }

    private function extractErrorMessage(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        foreach (['message', 'error', 'title', 'detail', 'otpResponseMessage'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key]) && trim($payload[$key]) !== '') {
                return trim($payload[$key]);
            }
        }

        $data = $payload['data'] ?? null;
        if (is_array($data)) {
            foreach (['message', 'error', 'title'] as $key) {
                if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                    return trim($data[$key]);
                }
            }
        }

        $errors = $payload['errors'] ?? null;
        if (is_array($errors)) {
            foreach ($errors as $error) {
                if (is_string($error) && trim($error) !== '') {
                    return trim($error);
                }
                if (is_array($error) && isset($error[0]) && is_string($error[0])) {
                    return trim($error[0]);
                }
            }
        }

        return null;
    }

    private function normaliseStatus(string $status): string
    {
        return match (strtolower($status)) {
            'success', 'successful', 'completed', 'paid' => 'completed',
            'failed', 'failure', 'declined' => 'failed',
            default => 'pending',
        };
    }
}
