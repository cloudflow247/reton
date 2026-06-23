<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\StaticAccountRequest;
use App\Domain\Payments\Alatpay\Data\StaticAccountVerifyRequest;
use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Support\Str;

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

    public function __construct(
        private readonly AlatpayGateway $gateway,
        private readonly WalletService $wallets,
    ) {}

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

        $attributes = [
            'provider_reference' => $response->staticWalletId,
            'otp_tracking_id' => $response->otpTrackingId,
        ];

        // No OTP required: the provider already returned a live account number.
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
}
