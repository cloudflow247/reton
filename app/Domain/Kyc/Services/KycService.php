<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Services;

use App\Domain\Kyc\Contracts\KycVerificationGateway;
use App\Domain\Kyc\Enums\KycTier;
use App\Domain\Kyc\Exceptions\KycLimitExceededException;
use App\Domain\Kyc\Exceptions\KycVerificationException;
use App\Domain\Kyc\Models\UserKyc;
use App\Models\User;
use App\Support\Banking\AccountNameMatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Identity verification aligned with ALATPay static-wallet tiers.
 *
 * Tier 1 — basic profile (default): collection static wallet, lower limits.
 * Tier 2 — BVN verified via ALATPay OTP (default) or Dojah: individual static wallet on ALATPay.
 * Tier 3 — NIN + address verified via Dojah: highest limits.
 */
class KycService
{
    public function __construct(
        private readonly KycVerificationGateway $verification,
        private readonly KycAuditService $audit,
        private readonly AlatpayBvnVerificationService $alatpayBvn,
    ) {}

    public function forUser(User $user): UserKyc
    {
        return UserKyc::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            ['tier' => KycTier::Tier1],
        );
    }

    /**
     * CBN-aligned gate: verified BVN required before ALATPay funding or static VA.
     *
     * @return non-empty-string Verified 11-digit BVN (sent to ALATPay on collections)
     */
    public function assertBvnVerifiedForPayments(User $user): string
    {
        $profile = $this->forUser($user);
        $bvn = $profile->decryptedBvn();

        if ($bvn === null || $profile->bvn_verified_at === null) {
            throw ValidationException::withMessages([
                'bvn' => ['Verify your BVN before funding your wallet or opening a deposit account.'],
            ]);
        }

        return $bvn;
    }

    public function upgradeToTier2(User $user, string $bvn, string $dateOfBirth, ?string $ipAddress = null): UserKyc|string
    {
        if ($this->bvnProvider() === 'alatpay') {
            return $this->alatpayBvn->initiate($user, $bvn, $dateOfBirth, $ipAddress);
        }

        return $this->upgradeToTier2ViaDojah($user, $bvn, $dateOfBirth, $ipAddress);
    }

    public function confirmAlatpayTier2(User $user, string $otp, ?string $ipAddress = null): UserKyc
    {
        return $this->alatpayBvn->confirm($user, $otp, $ipAddress);
    }

    public function resendAlatpayTier2Otp(User $user, ?string $ipAddress = null): string
    {
        return $this->alatpayBvn->resend($user, $ipAddress);
    }

    public function hasPendingAlatpayBvn(User $user): bool
    {
        return $this->alatpayBvn->hasPending($user);
    }

    public function pendingAlatpayBvnHint(User $user): ?string
    {
        return $this->alatpayBvn->pendingHint($user);
    }

    public function bvnProvider(): string
    {
        return (string) config('services.kyc.bvn_provider', 'alatpay');
    }

    public function bvnDemoMode(): bool
    {
        return $this->bvnProvider() === 'alatpay'
            && (string) config('services.alatpay.driver', 'http') === 'fake';
    }

    private function upgradeToTier2ViaDojah(User $user, string $bvn, string $dateOfBirth, ?string $ipAddress = null): UserKyc
    {
        $bvn = (string) preg_replace('/\D/', '', $bvn);

        if (strlen($bvn) !== 11) {
            throw ValidationException::withMessages(['bvn' => ['Enter a valid 11-digit BVN.']]);
        }

        try {
            $dob = Carbon::parse($dateOfBirth);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date_of_birth' => ['Enter a valid date of birth.']]);
        }

        if ($dob->age < 18) {
            throw ValidationException::withMessages(['date_of_birth' => ['You must be at least 18 years old.']]);
        }

        return DB::transaction(function () use ($user, $bvn, $dob, $ipAddress): UserKyc {
            $kyc = $this->forUser($user)->fresh();

            if ($kyc->tier->isAtLeast(KycTier::Tier2)) {
                return $kyc;
            }

            $this->assertBvnAvailable($user, $bvn);

            try {
                $identity = $this->verification->verifyBvn($bvn);
            } catch (KycVerificationException $e) {
                $this->audit->record($user, 'bvn', $this->providerName(), 'failed', $e->getMessage(), $ipAddress);
                throw ValidationException::withMessages(['bvn' => [$e->getMessage()]]);
            }

            if ($identity->dateOfBirth === null || ! $identity->dateOfBirth->isSameDay($dob)) {
                $this->audit->record($user, 'bvn', $this->providerName(), 'failed', 'dob_mismatch', $ipAddress);
                throw ValidationException::withMessages(['date_of_birth' => ['Date of birth does not match BVN records.']]);
            }

            if (! AccountNameMatcher::matches($identity->fullName(), $user->name)) {
                $this->audit->record($user, 'bvn', $this->providerName(), 'failed', 'name_mismatch', $ipAddress, [
                    'matched_tokens' => false,
                ]);
                throw ValidationException::withMessages(['bvn' => ['BVN name must match your Reton profile name. Update your profile or use the BVN registered to this account.']]);
            }

            $kyc->storeBvn($bvn);
            $kyc->date_of_birth = $dob;
            $kyc->tier = KycTier::Tier2;
            $kyc->save();

            $this->audit->record($user, 'bvn', $this->providerName(), 'success', null, $ipAddress, [
                'tier' => 2,
            ]);

            return $kyc->refresh();
        });
    }

    public function upgradeToTier3(
        User $user,
        string $nin,
        string $addressLine1,
        string $city,
        string $state,
        ?string $ipAddress = null,
    ): UserKyc {
        $kyc = $this->forUser($user);

        if (! $kyc->tier->isAtLeast(KycTier::Tier2)) {
            throw KycLimitExceededException::tierRequired(KycTier::Tier2->value);
        }

        $nin = (string) preg_replace('/\D/', '', $nin);

        if (strlen($nin) !== 11) {
            throw ValidationException::withMessages(['nin' => ['Enter a valid 11-digit NIN.']]);
        }

        if (trim($addressLine1) === '' || trim($city) === '' || trim($state) === '') {
            throw ValidationException::withMessages(['address_line1' => ['Complete your residential address.']]);
        }

        return DB::transaction(function () use ($user, $kyc, $nin, $addressLine1, $city, $state, $ipAddress): UserKyc {
            $kyc = $kyc->fresh();

            if ($kyc->tier === KycTier::Tier3) {
                return $kyc;
            }

            $this->assertNinAvailable($user, $nin);

            try {
                $identity = $this->verification->verifyNin($nin);
            } catch (KycVerificationException $e) {
                $this->audit->record($user, 'nin', $this->providerName(), 'failed', $e->getMessage(), $ipAddress);
                throw ValidationException::withMessages(['nin' => [$e->getMessage()]]);
            }

            if (! AccountNameMatcher::matches($identity->fullName(), $user->name)) {
                $this->audit->record($user, 'nin', $this->providerName(), 'failed', 'name_mismatch', $ipAddress);
                throw ValidationException::withMessages(['nin' => ['NIN name must match your Reton profile name.']]);
            }

            if ($kyc->date_of_birth !== null && $identity->dateOfBirth !== null && ! $identity->dateOfBirth->isSameDay($kyc->date_of_birth)) {
                $this->audit->record($user, 'nin', $this->providerName(), 'failed', 'dob_mismatch', $ipAddress);
                throw ValidationException::withMessages(['nin' => ['NIN date of birth does not match your verified BVN record.']]);
            }

            $kyc->storeNin($nin);
            $kyc->address_line1 = trim($addressLine1);
            $kyc->city = trim($city);
            $kyc->state = trim($state);
            $kyc->tier = KycTier::Tier3;
            $kyc->save();

            $this->audit->record($user, 'nin', $this->providerName(), 'success', null, $ipAddress, [
                'tier' => 3,
            ]);

            return $kyc->refresh();
        });
    }

    /** @return array<string, mixed> */
    public function limitsFor(UserKyc $kyc): array
    {
        $tier = $kyc->tier->value;
        $config = (array) config("reton.kyc.tiers.{$tier}", []);

        return [
            'tier' => $tier,
            'label' => $kyc->tier->label(),
            'single_transaction_max' => (int) ($config['single_transaction_max'] ?? 0),
            'daily_inflow_max' => (int) ($config['daily_inflow_max'] ?? 0),
            'wallet_balance_max' => (int) ($config['wallet_balance_max'] ?? 0),
            'static_wallet_type' => $kyc->staticWalletType()->value,
        ];
    }

    private function providerName(): string
    {
        if ($this->bvnProvider() === 'alatpay') {
            return config('services.alatpay.driver') === 'fake' ? 'alatpay_fake' : 'alatpay';
        }

        return config('services.dojah.driver') === 'fake' ? 'dojah_fake' : 'dojah';
    }

    private function assertBvnAvailable(User $user, string $bvn): void
    {
        $hash = hash('sha256', $bvn);
        $taken = UserKyc::query()
            ->where('user_id', '!=', $user->getKey())
            ->where('bvn_hash', $hash)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'bvn' => ['This BVN is already linked to another account. Sign in to that account, or contact support to release it.'],
            ]);
        }
    }

    private function assertNinAvailable(User $user, string $nin): void
    {
        $hash = hash('sha256', $nin);
        $taken = UserKyc::query()
            ->where('user_id', '!=', $user->getKey())
            ->where('nin_hash', $hash)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'nin' => ['This NIN is already linked to another account. Sign in to that account, or contact support to release it.'],
            ]);
        }
    }
}
