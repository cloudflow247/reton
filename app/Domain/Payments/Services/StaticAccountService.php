<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Kyc\Services\KycLimitService;
use App\Domain\Kyc\Services\KycService;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\StaticAccountRequest;
use App\Domain\Payments\Alatpay\Data\StaticAccountResponse;
use App\Domain\Payments\Alatpay\Data\StaticAccountTransaction;
use App\Domain\Payments\Alatpay\Data\StaticAccountVerifyRequest;
use App\Domain\Payments\Alatpay\Exceptions\AlatpayException;
use App\Domain\Payments\Enums\DepositStatus;
use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Banking\FundingAccountName;
use App\Support\Money\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Provisions and funds permanent AlatPay static accounts.
 *
 * Provisioning is a two-step OTP flow (provision -> verify), except when the
 * provider returns an account number immediately (e.g. a Collection wallet that
 * needs no OTP), in which case the account is activated on provision. Funding is
 * poll-driven: see poll()/credit() — every credit flows through the audited
 * WalletService ledger path.
 */
class StaticAccountService
{
    private const PROVIDER = 'alatpay';

    private const STATIC_PROVIDER = 'alatpay_static';

    public function __construct(
        private readonly AlatpayGateway $gateway,
        private readonly WalletService $wallets,
        private readonly KycService $kyc,
        private readonly KycLimitService $kycLimits,
    ) {}

    /**
     * Provision (or return) the wallet's ALATPay static account using the user's KYC tier.
     */
    public function provisionForWallet(User $user, Wallet $wallet): StaticAccount
    {
        $this->kyc->assertBvnVerifiedForPayments($user);

        $existing = StaticAccount::query()
            ->where('wallet_id', $wallet->getKey())
            ->latest()
            ->first();

        if ($existing !== null && $existing->isActive() && filled($existing->account_number)) {
            return $existing;
        }

        // Incomplete rows (failed mid-provision) should be retried, not stuck forever.
        if ($existing !== null && ! $existing->isActive()) {
            $existing->delete();
        }

        $profile = $this->kyc->forUser($user);
        $type = $profile->staticWalletType();
        $bvn = $type === StaticWalletType::Individual ? $profile->decryptedBvn() : null;

        if ($type === StaticWalletType::Individual && $bvn === null) {
            throw ValidationException::withMessages([
                'kyc' => ['Verify your BVN (Tier 2) before opening an individual deposit account.'],
            ]);
        }

        return $this->provision($user, $wallet, $type, $bvn);
    }

    /**
     * Persist an ALATPay Individual VA that was already verified (BVN OTP / recovery).
     * Does not call ALATPay again.
     */
    public function linkVerifiedIndividualAccount(
        User $user,
        string $providerReference,
        string $accountNumber,
        ?string $accountName = null,
        ?string $bankName = 'ALAT by Wema',
    ): StaticAccount {
        $wallet = $this->wallets->open($user, 'NGN');

        $ownedByOther = StaticAccount::query()
            ->where('account_number', $accountNumber)
            ->where('user_id', '!=', $user->getKey())
            ->exists();

        if ($ownedByOther) {
            throw ValidationException::withMessages([
                'bvn' => ['This deposit account is already linked to another Reton user.'],
            ]);
        }

        $existing = StaticAccount::query()
            ->where('wallet_id', $wallet->getKey())
            ->latest()
            ->first();

        $attributes = [
            'user_id' => $user->getKey(),
            'provider' => self::PROVIDER,
            'provider_reference' => $providerReference,
            'wallet_type' => StaticWalletType::Individual,
            'status' => StaticAccountStatus::Active,
            'account_number' => $accountNumber,
            'account_name' => FundingAccountName::display($accountName, (string) $user->name),
            'bank_name' => $bankName ?? 'ALAT by Wema',
            'otp_tracking_id' => null,
            'email' => $user->email,
        ];

        if ($existing !== null) {
            $existing->update($attributes);

            return $existing->refresh();
        }

        return StaticAccount::create([
            'wallet_id' => $wallet->getKey(),
            ...$attributes,
        ]);
    }

    /**
     * @see linkVerifiedIndividualAccount()
     */
    public function linkFromProviderResponse(User $user, StaticAccountResponse $response): StaticAccount
    {
        return $this->linkVerifiedIndividualAccount(
            $user,
            $response->providerReference,
            $response->accountNumber,
            $response->accountName,
            $response->bankName,
        );
    }

    public function provision(User $user, Wallet $wallet, StaticWalletType $type, ?string $bvn = null): StaticAccount
    {
        $bvn = $type === StaticWalletType::Collection
            ? (string) config('services.alatpay.business_bvn')
            : (string) $bvn;

        $account = StaticAccount::create([
            'wallet_id' => $wallet->getKey(),
            'user_id' => $user->getKey(),
            'provider' => self::PROVIDER,
            'wallet_type' => $type,
            'status' => StaticAccountStatus::PendingOtp,
            'email' => $user->email,
        ]);

        try {
            $response = $this->gateway->provisionStaticAccount(new StaticAccountRequest(
                walletType: $type->providerCode(),
                bvn: $bvn,
                email: (string) $user->email,
                reference: 'SA-'.Str::upper((string) Str::ulid()),
            ));
        } catch (AlatpayException $e) {
            $account->delete();

            Log::warning('Static account provision failed', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'wallet' => [$e->userFacingMessage('Could not open your deposit account. Please try again in a moment.')],
            ]);
        } catch (\Throwable $e) {
            $account->delete();

            Log::error('Static account provision crashed', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'wallet' => ['Could not open your deposit account. Please try again in a moment.'],
            ]);
        }

        if ($response->accountNumber !== null) {
            $ownedByOther = StaticAccount::query()
                ->where('account_number', $response->accountNumber)
                ->where('user_id', '!=', $user->getKey())
                ->exists();

            if ($ownedByOther) {
                $account->delete();

                throw ValidationException::withMessages([
                    'bvn' => ['This deposit account is already linked to another Reton user.'],
                ]);
            }
        }

        $attributes = [
            'provider_reference' => $response->staticWalletId,
            'otp_tracking_id' => $response->otpTrackingId,
        ];

        // No OTP required: the provider already returned a live account number
        // (including recovered duplicate-BVN accounts).
        if ($response->otpTrackingId === null && $response->accountNumber !== null) {
            $attributes['account_number'] = $response->accountNumber;
            $attributes['account_name'] = FundingAccountName::display($response->accountName, (string) $user->name);
            $attributes['bank_name'] = 'ALAT by Wema';
            $attributes['status'] = StaticAccountStatus::Active;
        }

        $account->update($attributes);

        return $account->refresh();
    }

    public function verify(StaticAccount $account, string $otp): StaticAccount
    {
        if ($account->isActive()) {
            return $account;
        }

        $response = $this->gateway->verifyStaticAccount(new StaticAccountVerifyRequest(
            staticWalletId: (string) $account->provider_reference,
            otp: $otp,
            trackingId: (string) $account->otp_tracking_id,
        ));

        $account->loadMissing('user');

        $account->update([
            'account_number' => $response->accountNumber,
            'account_name' => FundingAccountName::display(
                $response->accountName,
                (string) ($account->user?->name ?? ''),
            ),
            'bank_name' => $response->bankName,
            'status' => StaticAccountStatus::Active,
        ]);

        return $account->refresh();
    }

    /**
     * Fetch inbound transactions for a static account and credit any new
     * successful ones to the owner's wallet via the ledger.
     *
     * Returns the number of new credits applied in this poll.
     */
    public function poll(StaticAccount $account): int
    {
        if (! $account->isActive() || $account->account_number === null) {
            return 0;
        }

        $credited = 0;

        foreach ($this->gateway->fetchStaticAccountTransactions(
            $account->account_number,
            staticWalletId: $account->provider_reference,
        ) as $txn) {
            if (! $txn->isSuccessful() || $txn->amountMinor() <= 0) {
                continue;
            }

            if ($txn->transactionId === '') {
                Log::warning('Skipping static-account transaction with empty id', [
                    'static_account_id' => $account->id,
                    'account_number' => $account->account_number,
                    'amount_major' => $txn->amountMajor,
                ]);

                continue;
            }

            $alreadyRecorded = Deposit::where('provider', self::STATIC_PROVIDER)
                ->where('provider_reference', $txn->transactionId)
                ->exists();

            if ($alreadyRecorded) {
                continue;
            }

            try {
                $this->credit($account, $txn);
                $credited++;
            } catch (UniqueConstraintViolationException) {
                // A concurrent poll already credited this transaction; the unique
                // (provider, provider_reference) / idempotency_key constraints held.
                // Treat as already-credited: skip without aborting the loop.
                continue;
            } catch (\Throwable $e) {
                // KYC limits / ledger failures must not abort the rest of the poll
                // (or the scheduled command for other accounts).
                report($e);
                Log::error('Static account credit failed', [
                    'static_account_id' => $account->id,
                    'provider_reference' => $txn->transactionId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $account->update(['last_polled_at' => now()]);

        return $credited;
    }

    /**
     * Resolve the user's active permanent funding account for dashboard / receive UI.
     *
     * Prefers the wallet-linked Active VA, then falls back to any Active VA on the user
     * (production rows sometimes attach by user_id first). Never uses latestOfMany +
     * constrained eager-load — that combination can hide a valid Active account.
     */
    public function activeFundingAccountFor(User $user, ?Wallet $wallet = null): ?StaticAccount
    {
        $base = StaticAccount::query()
            ->where('status', StaticAccountStatus::Active)
            ->whereNotNull('account_number')
            ->where('account_number', '!=', '');

        if ($wallet instanceof Wallet) {
            $onWallet = (clone $base)
                ->where('wallet_id', $wallet->getKey())
                ->latest()
                ->first();

            if ($onWallet instanceof StaticAccount) {
                return $onWallet;
            }
        }

        $onUser = (clone $base)
            ->where('user_id', $user->getKey())
            ->latest()
            ->first();

        return $onUser instanceof StaticAccount ? $onUser : null;
    }

    /**
     * On-demand poll when a user opens Add Money / Dashboard so VA deposits
     * credit even if the minute scheduler is delayed or disabled.
     *
     * @throws \Throwable when the ALATPay fetch fails (callers may flash a message)
     */
    public function pollActiveForUser(User $user, int $staleAfterSeconds = 0): int
    {
        $account = $this->activeFundingAccountFor($user);

        if ($account === null) {
            return 0;
        }

        if (
            $staleAfterSeconds > 0
            && $account->last_polled_at !== null
            && $account->last_polled_at->gt(now()->subSeconds($staleAfterSeconds))
        ) {
            return 0;
        }

        return $this->poll($account);
    }

    /**
     * Poll every active VA and return a summary for admin ops.
     *
     * @return array{driver: string, credited: int, accounts: int, error: ?string}
     */
    public function syncAllActive(): array
    {
        $driver = (string) config('services.alatpay.driver', 'http');

        if ($driver === 'fake') {
            return [
                'driver' => $driver,
                'credited' => 0,
                'accounts' => 0,
                'error' => 'ALATPay driver is still "fake". Switch to Live HTTP in Admin → Integrations, save, then sync again.',
            ];
        }

        $credited = 0;
        $accounts = 0;

        try {
            StaticAccount::query()
                ->where('status', StaticAccountStatus::Active->value)
                ->whereNotNull('account_number')
                ->orderBy('last_polled_at')
                ->each(function (StaticAccount $account) use (&$credited, &$accounts): void {
                    $accounts++;
                    $credited += $this->poll($account);
                });
        } catch (\Throwable $e) {
            report($e);

            return [
                'driver' => $driver,
                'credited' => $credited,
                'accounts' => $accounts,
                'error' => $e->getMessage(),
            ];
        }

        return [
            'driver' => $driver,
            'credited' => $credited,
            'accounts' => $accounts,
            'error' => null,
        ];
    }

    private function credit(StaticAccount $account, StaticAccountTransaction $txn): void
    {
        DB::transaction(function () use ($account, $txn): void {
            $wallet = Wallet::findOrFail($account->wallet_id);
            $user = User::findOrFail($account->user_id);
            $amount = Money::of($txn->amountMinor(), $wallet->currency);

            $this->kycLimits->assertCanCredit($user, $wallet, $amount);

            $deposit = Deposit::create([
                'reference' => 'SDEP-'.$txn->transactionId,
                'user_id' => $account->user_id,
                'wallet_id' => $account->wallet_id,
                'provider' => self::STATIC_PROVIDER,
                'provider_reference' => $txn->transactionId,
                'status' => DepositStatus::Pending,
                'amount' => $txn->amountMinor(),
                'currency' => $wallet->currency,
                'metadata' => [
                    'channel' => 'static_account',
                    'static_account_id' => $account->id,
                    'narration' => $txn->narration,
                ],
            ]);

            $description = filled($txn->narration)
                ? 'Bank transfer — '.$txn->narration
                : 'Wallet funding via dedicated account';

            $transaction = $this->wallets->fund(
                $wallet,
                $amount,
                $txn->transactionId,
                [
                    'deposit_id' => $deposit->id,
                    'provider' => self::STATIC_PROVIDER,
                    'bank_transfer' => [
                        'narration' => $txn->narration,
                        'provider_reference' => $txn->transactionId,
                        'channel' => 'static_account',
                    ],
                ],
                $description,
            );

            $deposit->update([
                'status' => DepositStatus::Completed,
                'transaction_id' => $transaction->id,
                'paid_at' => now(),
                'metadata' => array_merge((array) ($deposit->metadata ?? []), [
                    'ledger_description' => $description,
                    'bank_transfer' => [
                        'narration' => $txn->narration,
                        'provider_reference' => $txn->transactionId,
                        'channel' => 'static_account',
                    ],
                ]),
            ]);
        });
    }
}
