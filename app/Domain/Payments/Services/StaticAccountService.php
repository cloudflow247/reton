<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Kyc\Services\KycLimitService;
use App\Domain\Kyc\Services\KycService;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\StaticAccountRequest;
use App\Domain\Payments\Alatpay\Data\StaticAccountTransaction;
use App\Domain\Payments\Alatpay\Data\StaticAccountVerifyRequest;
use App\Domain\Payments\Enums\DepositStatus;
use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
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

        if ($existing !== null) {
            return $existing;
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

        $response = $this->gateway->provisionStaticAccount(new StaticAccountRequest(
            walletType: $type->providerCode(),
            bvn: $bvn,
            email: (string) $user->email,
            reference: 'SA-'.Str::upper((string) Str::ulid()),
        ));

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
            $attributes['account_name'] = $response->accountName;
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

        $account->update([
            'account_number' => $response->accountNumber,
            'account_name' => $response->accountName,
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

        foreach ($this->gateway->fetchStaticAccountTransactions($account->account_number) as $txn) {
            if (! $txn->isSuccessful() || $txn->amountMinor() <= 0) {
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
            }
        }

        $account->update(['last_polled_at' => now()]);

        return $credited;
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

            $transaction = $this->wallets->fund(
                $wallet,
                $amount,
                $txn->transactionId, // ledger idempotency key
                ['deposit_id' => $deposit->id, 'provider' => self::STATIC_PROVIDER],
            );

            $deposit->update([
                'status' => DepositStatus::Completed,
                'transaction_id' => $transaction->id,
                'paid_at' => now(),
            ]);
        });
    }
}
