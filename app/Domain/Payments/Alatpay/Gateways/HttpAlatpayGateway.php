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
use App\Domain\Payments\Alatpay\Data\StaticAccountSummary;
use App\Domain\Payments\Alatpay\Data\StaticAccountTransaction;
use App\Domain\Payments\Alatpay\Data\StaticAccountVerifyRequest;
use App\Domain\Payments\Alatpay\Data\TransferRequest;
use App\Domain\Payments\Alatpay\Data\TransferResponse;
use App\Domain\Payments\Alatpay\Exceptions\AlatpayException;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
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
        $this->assertConfigured('createPaymentLink');

        $payload = array_filter([
            'businessId' => config('services.alatpay.business_id'),
            'amount' => $request->amount->amount,
            'currency' => $request->amount->currency,
            'orderId' => $request->reference,
            'title' => $request->title,
            'description' => $request->description !== '' ? $request->description : null,
            'customer' => array_filter([
                'email' => $request->customerEmail !== '' ? $request->customerEmail : null,
                'name' => $request->customerName !== '' ? $request->customerName : null,
                'phone' => $request->customerPhone,
                'bvn' => $request->customerBvn,
            ]),
            'redirectUrl' => $request->redirectUrl,
            'expiresAt' => $request->expiresAt,
            // Omit "*" / null - ALATPay treats missing channel as all methods.
            'channel' => ($request->channel !== null && $request->channel !== '*')
                ? $request->channel
                : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        $response = $this->client()->post('/payment-link/api/v1/links', $payload);

        if (! $response->successful()) {
            throw AlatpayException::requestFailed(
                'createPaymentLink',
                $response->status(),
                $this->extractErrorMessage($response->json()),
            );
        }

        $data = (array) $response->json('data', []);

        $paymentLinkUrl = (string) ($data['url'] ?? $data['paymentLink'] ?? $data['paymentUrl'] ?? '');

        if ($paymentLinkUrl === '') {
            throw AlatpayException::requestFailed(
                'createPaymentLink',
                $response->status() ?: 502,
                'ALATPay did not return a payment link URL. Confirm Payment Link is enabled for this business.',
            );
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
            providerReference: (string) ($data['transactionId'] ?? $data['reference'] ?? $providerReference),
            status: $this->normaliseStatus((string) ($data['status'] ?? 'pending')),
            amount: $this->amountMinorFromPayload($data),
            currency: (string) ($data['currency'] ?? 'NGN'),
            narration: $this->stringOrNull($data['narration'] ?? $data['Narration'] ?? $data['paymentDescription'] ?? null),
            payerName: $this->stringOrNull($data['customerName'] ?? $data['senderName'] ?? $data['payerName'] ?? $data['accountName'] ?? null),
            bankName: $this->stringOrNull($data['bankName'] ?? $data['sourceBank'] ?? $data['BankName'] ?? null),
            channel: $this->stringOrNull($data['channel'] ?? $data['paymentChannel'] ?? 'bank_transfer'),
            paidAt: $this->stringOrNull($data['transactionDate'] ?? $data['paidAt'] ?? $data['settlementDate'] ?? null),
        );
    }

    /**
     * ALATPay on apibox (docs.alatpay.ng) is collection-only. Outbound NIP payouts
     * require Wema's separate Debit Wallet API (playground.alat.ng) with its own
     * access key, securityInfo, and bank-profiled auth callback - not the same
     * merchant subscription used for Static Wallet / bank-transfer collections.
     *
     * @see https://docs.alatpay.ng/
     * @see https://playground.alat.ng/api-debit-wallet
     */
    public function supportsOutboundTransfers(): bool
    {
        return (bool) config('services.alatpay.debit_wallet.enabled', false)
            && filled(config('services.alatpay.debit_wallet.access_key'));
    }

    public function initiateTransfer(TransferRequest $request): TransferResponse
    {
        if (! $this->supportsOutboundTransfers()) {
            throw AlatpayException::requestFailed(
                'initiateTransfer',
                503,
                'Outbound bank transfers are not enabled. ALATPay collections cannot disburse; configure Wema Debit Wallet credentials.',
            );
        }

        // Debit Wallet: POST /debit-wallet/api/Shared/ProcessClientTransfer
        // Requires bank-onboarded access key + securityInfo callback - wire when live.
        throw AlatpayException::requestFailed(
            'initiateTransfer',
            501,
            'Wema Debit Wallet payout adapter is not implemented yet.',
        );
    }

    public function fetchTransfer(string $providerReference): ?RemoteTransaction
    {
        if (! $this->supportsOutboundTransfers()) {
            return null;
        }

        return null;
    }

    /**
     * Health-check against Get Static Wallets.
     *
     * @see https://docs.alatpay.ng/static-wallet - GET /alatpay-wallet/api/v1/staticaccount
     */
    public function pingStaticWallet(): void
    {
        $this->assertConfigured('pingStaticWallet');

        try {
            $response = $this->sendWithSessionRetry(
                fn () => $this->client()->get('/alatpay-wallet/api/v1/staticaccount', [
                    'PageNumber' => 1,
                    'Limit' => 1,
                    'Status' => 1,
                    'BusinessId' => (string) config('services.alatpay.business_id'),
                ]),
            );
        } catch (ConnectionException|RequestException $e) {
            throw AlatpayException::requestFailed(
                'pingStaticWallet',
                503,
                'Could not reach ALATPay Static Wallet. Check Base URL (https://apibox.alatpay.ng).',
            );
        }

        $payload = $response->json();

        if ($response->successful()) {
            return;
        }

        Log::warning('ALATPay pingStaticWallet failed', [
            'status' => $response->status(),
            'base_url' => config('services.alatpay.base_url'),
            'body' => $payload ?? $response->body(),
        ]);

        throw AlatpayException::requestFailed(
            'pingStaticWallet',
            $response->status(),
            $this->extractErrorMessage($payload) ?? $this->authFailureHint($response->status()),
        );
    }

    /**
     * Create Individual (1) or Collection (2) static wallet - Step 1.
     *
     * @see https://docs.alatpay.ng/static-wallet - POST /alatpay-wallet/api/v1/staticaccount
     */
    public function provisionStaticAccount(StaticAccountRequest $request): StaticAccountProvisionResponse
    {
        $this->assertConfigured('provisionStaticAccount');

        try {
            $response = $this->sendWithSessionRetry(
                fn () => $this->client()->post(
                    '/alatpay-wallet/api/v1/staticaccount',
                    array_filter([
                        'businessId' => (string) config('services.alatpay.business_id'),
                        'staticWalletType' => $request->walletType,
                        'bvn' => $request->bvn,
                        'email' => $request->email,
                    ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                ),
            );
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

            $message = $this->extractErrorMessage($payload);

            if ($this->isDuplicateIndividualBvnMessage($message)) {
                $recovered = $this->recoverExistingIndividualAccount(
                    $request->email,
                    ...$request->recoveryEmails,
                );

                if ($recovered !== null) {
                    return $recovered;
                }
            }

            throw AlatpayException::requestFailed(
                'provisionStaticAccount',
                $response->status(),
                $message ?? $this->authFailureHint($response->status()),
            );
        }

        $data = $this->unwrapPayload($payload);
        $root = is_array($payload) ? $payload : [];

        if ($this->looksLikeSoftFailure($root, $data)) {
            $message = $this->extractErrorMessage($payload) ?? 'ALATPay rejected the BVN request.';

            if ($this->isDuplicateIndividualBvnMessage($message)) {
                $recovered = $this->recoverExistingIndividualAccount(
                    $request->email,
                    ...$request->recoveryEmails,
                );

                if ($recovered !== null) {
                    return $recovered;
                }
            }

            throw AlatpayException::requestFailed(
                'provisionStaticAccount',
                400,
                $message,
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

    /**
     * Validate OTP and finalise wallet - Step 2.
     *
     * @see https://docs.alatpay.ng/static-wallet - POST .../validateAndCreate
     */
    public function verifyStaticAccount(StaticAccountVerifyRequest $request): StaticAccountResponse
    {
        $this->assertConfigured('verifyStaticAccount');

        try {
            $response = $this->sendWithSessionRetry(
                fn () => $this->client()->post('/alatpay-wallet/api/v1/staticaccount/validateAndCreate', [
                    'staticWalletId' => $request->staticWalletId,
                    'businessId' => (string) config('services.alatpay.business_id'),
                    'otp' => $request->otp,
                    'trackingId' => $request->trackingId,
                ]),
            );
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

    /**
     * @return list<StaticAccountSummary>
     */
    public function listStaticAccounts(int $page = 1, int $limit = 50, int $status = 1): array
    {
        $this->assertConfigured('listStaticAccounts');

        $response = $this->sendWithSessionRetry(
            fn () => $this->client()->get('/alatpay-wallet/api/v1/staticaccount', [
                'PageNumber' => $page,
                'Limit' => $limit,
                'Status' => $status,
                'BusinessId' => (string) config('services.alatpay.business_id'),
            ]),
        );

        if (! $response->successful()) {
            throw AlatpayException::requestFailed(
                'listStaticAccounts',
                $response->status(),
                $this->extractErrorMessage($response->json()),
            );
        }

        $payload = $response->json();
        $rows = (array) (is_array($payload)
            ? ($payload['staticAccountResponses'] ?? $payload['data']['staticAccountResponses'] ?? [])
            : []);

        $summaries = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $summaries[] = new StaticAccountSummary(
                id: (string) ($row['id'] ?? ''),
                walletType: (int) ($row['walletType'] ?? 0),
                status: (int) ($row['status'] ?? 0),
                accountNumber: isset($row['accountNumber']) ? (string) $row['accountNumber'] : null,
                accountName: isset($row['accountName']) ? (string) $row['accountName'] : null,
                email: isset($row['email']) ? (string) $row['email'] : null,
            );
        }

        return $summaries;
    }

    /**
     * Attempt to move Wema bank alerts onto the merchant/CEO contact email.
     * ALATPay does not publicly document this endpoint - we try known update
     * shapes and surface a clear support message when the provider rejects them.
     */
    public function updateStaticAccountEmail(string $staticWalletId, string $email): void
    {
        $this->assertConfigured('updateStaticAccountEmail');

        $email = strtolower(trim($email));
        $payload = array_filter([
            'businessId' => (string) config('services.alatpay.business_id'),
            'id' => $staticWalletId,
            'staticWalletId' => $staticWalletId,
            'email' => $email,
        ], static fn (mixed $value): bool => $value !== '');

        $attempts = [
            ['method' => 'put', 'path' => '/alatpay-wallet/api/v1/staticaccount'],
            ['method' => 'post', 'path' => '/alatpay-wallet/api/v1/staticaccount/update'],
            ['method' => 'patch', 'path' => '/alatpay-wallet/api/v1/staticaccount/'.$staticWalletId],
        ];

        $lastStatus = 0;
        $lastMessage = null;

        foreach ($attempts as $attempt) {
            try {
                $response = $this->sendWithSessionRetry(
                    fn () => match ($attempt['method']) {
                        'put' => $this->client()->put($attempt['path'], $payload),
                        'patch' => $this->client()->patch($attempt['path'], $payload),
                        default => $this->client()->post($attempt['path'], $payload),
                    },
                );
            } catch (ConnectionException|RequestException $e) {
                throw AlatpayException::requestFailed(
                    'updateStaticAccountEmail',
                    503,
                    'Could not reach ALATPay while updating the deposit-account contact email.',
                );
            }

            $body = $response->json();
            $lastStatus = $response->status();
            $lastMessage = $this->extractErrorMessage(is_array($body) ? $body : null);

            if ($response->successful()) {
                $data = $this->unwrapPayload($body);
                $root = is_array($body) ? $body : [];

                if (! $this->looksLikeSoftFailure($root, $data)) {
                    Log::info('ALATPay updateStaticAccountEmail succeeded', [
                        'static_wallet_id' => $staticWalletId,
                        'path' => $attempt['path'],
                        'method' => $attempt['method'],
                    ]);

                    return;
                }
            }

            if (in_array($response->status(), [404, 405, 501], true)) {
                continue;
            }
        }

        Log::warning('ALATPay updateStaticAccountEmail unsupported or rejected', [
            'static_wallet_id' => $staticWalletId,
            'status' => $lastStatus,
            'message' => $lastMessage,
        ]);

        throw AlatpayException::requestFailed(
            'updateStaticAccountEmail',
            $lastStatus > 0 ? $lastStatus : 400,
            $lastMessage ?? 'ALATPay does not allow updating this contact email via API. Ask ALATPay support to set it.',
        );
    }

    /**
     * When ALATPay says the BVN already has an Individual wallet, reuse the
     * existing active account that matches any candidate contact email.
     */
    private function recoverExistingIndividualAccount(?string ...$emails): ?StaticAccountProvisionResponse
    {
        $candidates = array_values(array_unique(array_filter(array_map(
            static fn (?string $email): string => strtolower(trim((string) $email)),
            $emails,
        ))));

        if ($candidates === []) {
            return null;
        }

        try {
            for ($page = 1; $page <= 10; $page++) {
                $accounts = $this->listStaticAccounts($page, 50, 1);

                if ($accounts === []) {
                    break;
                }

                foreach ($accounts as $account) {
                    if (! $account->isIndividual() || ! $account->isActive()) {
                        continue;
                    }

                    $accountEmail = strtolower(trim((string) $account->email));

                    if ($accountEmail === '' || ! in_array($accountEmail, $candidates, true)) {
                        continue;
                    }

                    Log::info('ALATPay recovered existing Individual static account for duplicate BVN', [
                        'static_wallet_id' => $account->id,
                        'account_number' => $account->accountNumber,
                    ]);

                    return new StaticAccountProvisionResponse(
                        staticWalletId: $account->id,
                        otpTrackingId: null,
                        accountNumber: $account->accountNumber,
                        accountName: $account->accountName,
                        otpHint: 'Existing ALATPay deposit account linked for this BVN.',
                    );
                }

                if (count($accounts) < 50) {
                    break;
                }
            }
        } catch (AlatpayException|ConnectionException|RequestException $e) {
            Log::warning('ALATPay could not recover existing Individual static account', [
                'emails' => $candidates,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function isDuplicateIndividualBvnMessage(?string $message): bool
    {
        if ($message === null || $message === '') {
            return false;
        }

        return str_contains(
            strtolower($message),
            'bvn has been used to create an individual static account',
        );
    }

    /**
     * Wallet collection history for one static account.
     *
     * Docs require Status=1; we try that first, then retry without Status if no
     * rows match (portal "Settled" sometimes differs). Account numbers are
     * matched loosely (leading-zero / JSON number quirks).
     *
     * @see https://docs.alatpay.ng/static-wallet - GET .../staticaccount/collectionhistory
     *
     * @return array<int, StaticAccountTransaction>
     */
    public function fetchStaticAccountTransactions(
        string $accountNumber,
        int $page = 1,
        int $limit = 50,
        ?string $staticWalletId = null,
    ): array {
        $matched = $this->collectStaticAccountTransactions(
            $accountNumber,
            $page,
            $limit,
            $staticWalletId,
            includeStatusFilter: true,
        );

        if ($matched === []) {
            $matched = $this->collectStaticAccountTransactions(
                $accountNumber,
                $page,
                $limit,
                $staticWalletId,
                includeStatusFilter: false,
            );
        }

        return $matched;
    }

    /**
     * @return array<int, StaticAccountTransaction>
     */
    private function collectStaticAccountTransactions(
        string $accountNumber,
        int $page,
        int $limit,
        ?string $staticWalletId,
        bool $includeStatusFilter,
    ): array {
        $matched = [];
        $currentPage = max(1, $page);
        $maxPages = 20;
        $totalRowsSeen = 0;

        do {
            $query = [
                'PageNumber' => $currentPage,
                'Limit' => $limit,
                'PageSize' => $limit,
                'BusinessId' => (string) config('services.alatpay.business_id'),
                'AccountNumber' => $accountNumber,
            ];

            if ($includeStatusFilter) {
                $query['Status'] = 1;
            }

            if (filled($staticWalletId)) {
                $query['StaticAccountId'] = $staticWalletId;
            }

            $response = $this->sendWithSessionRetry(
                fn () => $this->client()->get('/alatpay-wallet/api/v1/staticaccount/collectionhistory', $query),
            );

            if (! $response->successful()) {
                Log::warning('ALATPay collectionhistory failed', [
                    'http_status' => $response->status(),
                    'account_number' => $accountNumber,
                    'with_status_filter' => $includeStatusFilter,
                    'body' => $response->json() ?? $response->body(),
                ]);

                // Status filter may be rejected by some tenants - let caller retry without it.
                if ($includeStatusFilter) {
                    return [];
                }

                throw AlatpayException::requestFailed(
                    'fetchStaticAccountTransactions',
                    $response->status(),
                    'Could not load ALATPay collection history.',
                );
            }

            $rows = $this->extractCollectionHistoryRows($response);
            $totalRowsSeen += count($rows);

            foreach ($rows as $row) {
                $rowAccount = (string) ($row['accountNumber'] ?? $row['AccountNumber'] ?? '');

                if ($rowAccount !== '' && ! self::staticAccountNumbersMatch($accountNumber, $rowAccount)) {
                    continue;
                }

                if ($rowAccount === '' && blank($staticWalletId)) {
                    continue;
                }

                $transactionId = (string) (
                    $row['staticAccountTransactionId']
                    ?? $row['StaticAccountTransactionId']
                    ?? $row['id']
                    ?? ''
                );

                if ($transactionId === '') {
                    $transactionId = 'sat-'.hash('sha256', implode('|', [
                        self::normalizeStaticAccountNumber($accountNumber),
                        (string) ($row['amount'] ?? $row['Amount'] ?? ''),
                        (string) ($row['transactionDate'] ?? $row['TransactionDate'] ?? ''),
                        (string) ($row['narration'] ?? $row['Narration'] ?? ''),
                    ]));
                }

                $matched[] = new StaticAccountTransaction(
                    transactionId: $transactionId,
                    status: StaticAccountTransaction::statusFromRow($row),
                    accountNumber: $accountNumber,
                    amountMajor: (float) ($row['amount'] ?? $row['Amount'] ?? 0),
                    narration: isset($row['narration'])
                        ? (string) $row['narration']
                        : (isset($row['Narration']) ? (string) $row['Narration'] : null),
                    notificationEmail: isset($row['notificationEmail'])
                        ? (string) $row['notificationEmail']
                        : null,
                );
            }

            /** @var array<string, mixed> $paging */
            $paging = (array) ($response->json('pagingData') ?? $response->json('data.pagingData') ?? []);
            $hasNext = (bool) ($paging['hasNext'] ?? $paging['HasNext'] ?? false);
            $currentPage++;
        } while ($hasNext && $currentPage <= $maxPages);

        if ($matched === [] && $totalRowsSeen > 0) {
            Log::warning('ALATPay collectionhistory returned rows but none matched account', [
                'account_number' => $accountNumber,
                'static_wallet_id' => $staticWalletId,
                'rows_seen' => $totalRowsSeen,
                'with_status_filter' => $includeStatusFilter,
            ]);
        }

        return $matched;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractCollectionHistoryRows(Response $response): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            return [];
        }

        $candidates = [
            $payload['staticAccountTransactionResponses'] ?? null,
            data_get($payload, 'data.staticAccountTransactionResponses'),
            data_get($payload, 'Data.staticAccountTransactionResponses'),
            data_get($payload, 'result.staticAccountTransactionResponses'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || $candidate === []) {
                continue;
            }

            if (! array_is_list($candidate)) {
                continue;
            }

            /** @var list<mixed> $candidate */
            $rows = [];
            foreach ($candidate as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }

            return $rows;
        }

        $data = $payload['data'] ?? null;

        if (is_array($data) && array_is_list($data) && $data !== []) {
            $first = $data[0] ?? null;
            if (is_array($first) && (isset($first['amount']) || isset($first['accountNumber']) || isset($first['staticAccountTransactionId']))) {
                $rows = [];
                foreach ($data as $row) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }

                return $rows;
            }
        }

        return [];
    }

    /**
     * Compare VA numbers after stripping non-digits and leading zeros so
     * "0450041659" matches JSON number 450041659.
     */
    public static function staticAccountNumbersMatch(string $expected, string $actual): bool
    {
        $left = self::normalizeStaticAccountNumber($expected);
        $right = self::normalizeStaticAccountNumber($actual);

        if ($left === '' || $right === '') {
            return false;
        }

        return $left === $right;
    }

    private static function normalizeStaticAccountNumber(string $accountNumber): string
    {
        $digits = preg_replace('/\D+/', '', $accountNumber) ?? '';

        return ltrim($digits, '0') ?: $digits;
    }

    private ?CookieJar $cookieJar = null;

    private ?string $sessionSubscriptionKey = null;

    private function client(): PendingRequest
    {
        $this->ensureMerchantSession();

        return Http::baseUrl($this->resolvedBaseUrl())
            ->timeout((int) config('services.alatpay.timeout', 12))
            ->connectTimeout(4)
            ->withOptions(['cookies' => $this->cookieJar ?? new CookieJar])
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Ocp-Apim-Subscription-Key' => (string) $this->sessionSubscriptionKey,
            ])
            ->acceptJson()
            ->asJson();
    }

    /**
     * Wema requires merchant login before Static Wallet calls. Login starts a cookie
     * session and returns subscriptionPrimaryKey for the business.
     */
    private function ensureMerchantSession(bool $forceRefresh = false): void
    {
        if (! $forceRefresh && $this->cookieJar !== null && filled($this->sessionSubscriptionKey)) {
            return;
        }

        $cacheKey = $this->merchantSessionCacheKey();

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)
                && isset($cached['cookies'])
                && is_array($cached['cookies'])
                && array_is_list($cached['cookies'])
                && $cached['cookies'] !== []
                && filled($cached['subscription_key'] ?? null)
            ) {
                $cookieList = [];
                foreach ($cached['cookies'] as $cookie) {
                    if (is_array($cookie)) {
                        $cookieList[] = $cookie;
                    }
                }

                if ($cookieList !== []) {
                    $this->cookieJar = $this->cookieJarFromArray($cookieList);
                    $this->sessionSubscriptionKey = (string) $cached['subscription_key'];

                    return;
                }
            }
        }

        $this->loginMerchant($cacheKey);
    }

    private function loginMerchant(string $cacheKey): void
    {
        $email = strtolower(trim((string) config('services.alatpay.merchant_email')));
        $password = (string) config('services.alatpay.merchant_password');
        $businessId = trim((string) config('services.alatpay.business_id'));

        if ($email === '' || $password === '' || $businessId === '') {
            throw AlatpayException::requestFailed(
                'login',
                503,
                'ALATPay merchant email, password, and Business ID are required in Admin → Integrations.',
            );
        }

        $jar = new CookieJar;
        $headers = ['Content-Type' => 'application/json'];
        $bootstrapKey = trim((string) config('services.alatpay.api_key'));

        if ($bootstrapKey !== '') {
            $headers['Ocp-Apim-Subscription-Key'] = $bootstrapKey;
        }

        try {
            $response = Http::baseUrl($this->resolvedBaseUrl())
                ->timeout((int) config('services.alatpay.timeout', 12))
                ->connectTimeout(4)
                ->withOptions(['cookies' => $jar])
                ->withHeaders($headers)
                ->acceptJson()
                ->asJson()
                ->post('/merchant-onboarding/api/v1/auth/login', [
                    'email' => $email,
                    'password' => $password,
                ]);
        } catch (ConnectionException|RequestException $e) {
            throw AlatpayException::requestFailed(
                'login',
                503,
                'Could not reach ALATPay login. Check Base URL (https://apibox.alatpay.ng).',
            );
        }

        $payload = $response->json();

        if (! $response->successful() || (is_array($payload) && ($payload['status'] ?? true) === false)) {
            Log::warning('ALATPay merchant login failed', [
                'status' => $response->status(),
                'body' => $payload ?? $response->body(),
            ]);

            throw AlatpayException::requestFailed(
                'login',
                $response->status(),
                $this->extractErrorMessage($payload) ?? 'ALATPay merchant login failed. Check email/password.',
            );
        }

        $data = is_array($payload) ? ($payload['data'] ?? []) : [];
        $businesses = is_array($data) ? ($data['businesses'] ?? []) : [];
        $subscriptionKey = '';

        if (is_array($businesses)) {
            foreach ($businesses as $business) {
                if (! is_array($business)) {
                    continue;
                }

                if ((string) ($business['id'] ?? '') === $businessId
                    && filled($business['subscriptionPrimaryKey'] ?? null)
                ) {
                    $subscriptionKey = (string) $business['subscriptionPrimaryKey'];
                    break;
                }
            }

            if ($subscriptionKey === '') {
                foreach ($businesses as $business) {
                    if (is_array($business) && filled($business['subscriptionPrimaryKey'] ?? null)) {
                        $subscriptionKey = (string) $business['subscriptionPrimaryKey'];
                        break;
                    }
                }
            }
        }

        if ($subscriptionKey === '') {
            $subscriptionKey = $bootstrapKey;
        }

        if ($subscriptionKey === '') {
            throw AlatpayException::requestFailed(
                'login',
                503,
                'ALATPay login succeeded but no subscriptionPrimaryKey was returned for this Business ID.',
            );
        }

        $this->cookieJar = $jar;
        $this->sessionSubscriptionKey = $subscriptionKey;

        Cache::put($cacheKey, [
            'cookies' => $this->cookieJarToArray($jar),
            'subscription_key' => $subscriptionKey,
        ], now()->addMinutes(40));
    }

    private function forgetMerchantSession(): void
    {
        $this->cookieJar = null;
        $this->sessionSubscriptionKey = null;
        Cache::forget($this->merchantSessionCacheKey());
    }

    /**
     * @param  callable(): Response  $request
     */
    private function sendWithSessionRetry(callable $request): Response
    {
        $response = $request();

        if ($response->status() !== 401) {
            return $response;
        }

        $this->forgetMerchantSession();
        $this->ensureMerchantSession(forceRefresh: true);

        return $request();
    }

    private function merchantSessionCacheKey(): string
    {
        $email = strtolower(trim((string) config('services.alatpay.merchant_email')));
        $businessId = trim((string) config('services.alatpay.business_id'));

        return 'alatpay:merchant_session:'.hash('sha256', $email.'|'.$businessId);
    }

    /**
     * @param  list<array<string, mixed>>  $cookies
     */
    private function cookieJarFromArray(array $cookies): CookieJar
    {
        $jar = new CookieJar;

        foreach ($cookies as $cookie) {
            $jar->setCookie(new SetCookie($cookie));
        }

        return $jar;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cookieJarToArray(CookieJar $jar): array
    {
        $out = [];

        foreach ($jar as $cookie) {
            $out[] = $cookie->toArray();
        }

        return $out;
    }

    private function authFailureHint(int $status): string
    {
        if (in_array($status, [401, 403], true)) {
            return 'ALATPay session rejected. Confirm merchant email/password and Business ID in Admin → Integrations, then Test connection.';
        }

        return 'ALATPay Static Wallet request failed.';
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
        if (blank(config('services.alatpay.business_id'))) {
            throw AlatpayException::requestFailed(
                $operation,
                503,
                'ALATPay Business ID is missing. Add it in Admin → Integrations.',
            );
        }

        if (blank(config('services.alatpay.merchant_email')) || blank(config('services.alatpay.merchant_password'))) {
            throw AlatpayException::requestFailed(
                $operation,
                503,
                'ALATPay merchant email/password are required. Add them in Admin → Integrations.',
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function amountMinorFromPayload(array $data): int
    {
        $raw = $data['amount'] ?? $data['Amount'] ?? 0;

        if (is_int($raw)) {
            // Values under 1000 with a decimal sibling are rare; treat ints as minor units
            // when they match our deposit convention (Fake + existing reconcile).
            return $raw;
        }

        if (is_float($raw) || (is_string($raw) && str_contains($raw, '.'))) {
            return (int) round(((float) $raw) * 100);
        }

        return (int) $raw;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
