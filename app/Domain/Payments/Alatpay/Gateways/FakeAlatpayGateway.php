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
use Illuminate\Support\Facades\Cache;

/**
 * AlatPay gateway for local development and tests. Deterministic; never touches
 * the network. Static-wallet OTP state is cache-backed so browser round-trips
 * (new container per request) can still confirm with demo OTP 123456.
 */
class FakeAlatpayGateway implements AlatpayGateway
{
    private const STATIC_WALLETS_CACHE_KEY = 'fake_alatpay:static_wallets';

    private const STATIC_WALLETS_TTL_SECONDS = 7200;

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
            paymentLinkUrl: 'https://pay.alatpay.test/'.$request->reference.($request->channel ? '?channel='.$request->channel : ''),
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
        $bvn = (string) preg_replace('/\D/', '', (string) $request->bvn);
        $email = strtolower(trim((string) $request->email));

        if ($bvn !== '' && $request->walletType === 1) {
            $existingId = $this->bvnIndex()[$bvn] ?? null;
            if (is_string($existingId) && $existingId !== '') {
                $existing = $this->staticWallets[$existingId]
                    ?? $this->cachedStaticWallets()[$existingId]
                    ?? null;

                if (is_array($existing) && ($existing['email'] ?? null) === $email) {
                    return new StaticAccountProvisionResponse(
                        staticWalletId: $existingId,
                        otpTrackingId: null,
                        accountNumber: $existing['accountNumber'],
                        accountName: 'RETON STATIC',
                        otpHint: 'Existing ALATPay deposit account linked for this BVN.',
                    );
                }

                throw AlatpayException::requestFailed(
                    'provisionStaticAccount',
                    400,
                    'BVN has been used to create an individual static account for this business before',
                );
            }
        }

        $staticWalletId = 'SW-'.$request->reference;
        $accountNumber = '04'.substr(preg_replace('/\D/', '', $request->reference).'00000000', 0, 8);

        if ($this->provisionImmediate) {
            $this->rememberStaticWallet($staticWalletId, [
                'accountNumber' => $accountNumber,
                'otpTrackingId' => null,
                'email' => $email,
                'walletType' => $request->walletType,
                'bvn' => $bvn,
            ]);

            return new StaticAccountProvisionResponse($staticWalletId, null, $accountNumber, 'RETON STATIC');
        }

        $this->rememberStaticWallet($staticWalletId, [
            'accountNumber' => $accountNumber,
            'otpTrackingId' => 'OTP-'.$request->reference,
            'email' => $email,
            'walletType' => $request->walletType,
            'bvn' => $bvn,
        ]);

        return new StaticAccountProvisionResponse(
            $staticWalletId,
            'OTP-'.$request->reference,
            null,
            null,
            'Demo mode: use verification code 123456 (no SMS is sent when ALATPay driver is fake).',
        );
    }

    /**
     * @return list<\App\Domain\Payments\Alatpay\Data\StaticAccountSummary>
     */
    public function listStaticAccounts(int $page = 1, int $limit = 50, int $status = 1): array
    {
        unset($page, $limit, $status);

        $summaries = [];

        foreach ($this->cachedStaticWallets() + $this->staticWallets as $id => $wallet) {
            if (! is_array($wallet)) {
                continue;
            }

            $summaries[] = new \App\Domain\Payments\Alatpay\Data\StaticAccountSummary(
                id: (string) $id,
                walletType: (int) ($wallet['walletType'] ?? 1),
                status: 1,
                accountNumber: $wallet['accountNumber'] ?? null,
                accountName: 'RETON STATIC',
                email: $wallet['email'] ?? null,
            );
        }

        return $summaries;
    }

    public function verifyStaticAccount(StaticAccountVerifyRequest $request): StaticAccountResponse
    {
        if ($request->otp !== '123456') {
            throw AlatpayException::requestFailed('verifyStaticAccount', 400, 'Invalid OTP.');
        }

        $wallet = $this->staticWallets[$request->staticWalletId]
            ?? $this->cachedStaticWallets()[$request->staticWalletId]
            ?? null;

        if ($wallet === null || $wallet['accountNumber'] === null) {
            throw AlatpayException::requestFailed('verifyStaticAccount', 404, 'Static wallet not found.');
        }

        return new StaticAccountResponse(
            providerReference: $request->staticWalletId,
            accountNumber: $wallet['accountNumber'],
            accountName: 'RETON STATIC',
        );
    }

    /**
     * @param  array{accountNumber: ?string, otpTrackingId: ?string, email?: ?string, walletType?: int, bvn?: string}  $wallet
     */
    private function rememberStaticWallet(string $staticWalletId, array $wallet): void
    {
        $this->staticWallets[$staticWalletId] = $wallet;

        $cached = $this->cachedStaticWallets();
        $cached[$staticWalletId] = $wallet;
        Cache::put(self::STATIC_WALLETS_CACHE_KEY, $cached, self::STATIC_WALLETS_TTL_SECONDS);

        $bvn = (string) ($wallet['bvn'] ?? '');
        if ($bvn !== '' && (int) ($wallet['walletType'] ?? 0) === 1) {
            $index = $this->bvnIndex();
            $index[$bvn] = $staticWalletId;
            Cache::put(self::STATIC_WALLETS_CACHE_KEY.':bvn', $index, self::STATIC_WALLETS_TTL_SECONDS);
        }
    }

    /**
     * @return array<string, string>
     */
    private function bvnIndex(): array
    {
        $cached = Cache::get(self::STATIC_WALLETS_CACHE_KEY.':bvn', []);

        return is_array($cached) ? $cached : [];
    }

    /**
     * @return array<string, array{accountNumber: ?string, otpTrackingId: ?string, email?: ?string, walletType?: int, bvn?: string}>
     */
    private function cachedStaticWallets(): array
    {
        $cached = Cache::get(self::STATIC_WALLETS_CACHE_KEY, []);

        return is_array($cached) ? $cached : [];
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

    public function pingStaticWallet(): void
    {
        // Fake driver always accepts configured credentials.
    }

    public function fetchStaticAccountTransactions(string $accountNumber, int $page = 1, int $limit = 50): array
    {
        return $this->staticTransactions[$accountNumber] ?? [];
    }
}
