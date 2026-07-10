<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Services;

use App\Domain\Kyc\Enums\KycTier;
use App\Domain\Kyc\Models\UserKyc;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\StaticAccountProvisionResponse;
use App\Domain\Payments\Alatpay\Data\StaticAccountRequest;
use App\Domain\Payments\Alatpay\Data\StaticAccountResponse;
use App\Domain\Payments\Alatpay\Data\StaticAccountVerifyRequest;
use App\Domain\Payments\Alatpay\Exceptions\AlatpayException;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Services\StaticAccountService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * BVN verification via ALATPay Static Wallet OTP (Individual wallet provision).
 *
 * On successful OTP (or duplicate-BVN recovery), the Individual VA is linked to
 * the user's Reton wallet immediately — no separate "Open account" step.
 *
 * @see https://docs.alatpay.ng/static-wallet
 */
class AlatpayBvnVerificationService
{
    private const CACHE_PREFIX = 'bvn_pending:';

    private const TTL_SECONDS = 900;

    public function __construct(
        private readonly AlatpayGateway $gateway,
        private readonly KycAuditService $audit,
    ) {}

    public function hasPending(User $user): bool
    {
        return Cache::has($this->cacheKey($user));
    }

    public function pendingHint(User $user): ?string
    {
        $pending = Cache::get($this->cacheKey($user));

        return is_array($pending) ? ($pending['hint'] ?? null) : null;
    }

    /**
     * Step 1 — ALATPay validates the BVN and sends an OTP to the linked phone.
     *
     * @return non-empty-string User-facing message
     */
    public function initiate(User $user, string $bvn, string $dateOfBirth, ?string $ipAddress = null): string
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

        $kyc = UserKyc::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            ['tier' => KycTier::Tier1],
        );

        if ($kyc->tier->isAtLeast(KycTier::Tier2)) {
            return 'Your BVN is already verified.';
        }

        $this->assertBvnAvailable($user, $bvn);
        $this->assertNotSandboxBvnOnLive($bvn);

        try {
            $response = $this->gateway->provisionStaticAccount(new StaticAccountRequest(
                walletType: StaticWalletType::Individual->providerCode(),
                bvn: $bvn,
                email: (string) $user->email,
                reference: 'BVN-'.Str::upper((string) Str::ulid()),
            ));
        } catch (AlatpayException $e) {
            if ($e->isDuplicateIndividualBvn()) {
                $this->audit->record($user, 'bvn', $this->providerName(), 'failed', 'duplicate_bvn_unmatched', $ipAddress);
            } else {
                $this->audit->record($user, 'bvn', $this->providerName(), 'failed', $e->getMessage(), $ipAddress);
            }

            throw ValidationException::withMessages([
                'bvn' => [$e->userFacingMessage($this->defaultProvisionFailureMessage())],
            ]);
        }

        if ($response->otpTrackingId === null && $response->accountNumber !== null) {
            $this->finalizeTier2Record($user, $bvn, $dob, $ipAddress);
            $this->linkDepositAccountFromProvision($user, $response);

            return 'BVN verified — your permanent deposit account is ready.';
        }

        if ($response->staticWalletId === '' || $response->otpTrackingId === null) {
            $this->audit->record($user, 'bvn', $this->providerName(), 'failed', 'no_otp', $ipAddress);
            throw ValidationException::withMessages([
                'bvn' => ['ALATPay could not start BVN verification. Check your integration settings and try again.'],
            ]);
        }

        Cache::put($this->cacheKey($user), [
            'bvn' => encrypt($bvn),
            'dob' => $dob->toDateString(),
            'static_wallet_id' => $response->staticWalletId,
            'tracking_id' => $response->otpTrackingId,
            'hint' => $response->otpHint ?? 'Enter the OTP sent to the phone linked to your BVN.',
        ], self::TTL_SECONDS);

        $this->audit->record($user, 'bvn', $this->providerName(), 'otp_sent', null, $ipAddress);

        if ($this->providerName() === 'alatpay_fake') {
            return 'Demo mode: enter verification code 123456 below (no SMS when ALATPay driver is fake).';
        }

        return 'We sent a verification code to the phone linked to your BVN. Enter it below to unlock funding.';
    }

    /** Step 2 — confirm OTP, activate Tier 2, and link the deposit VA. */
    public function confirm(User $user, string $otp, ?string $ipAddress = null): UserKyc
    {
        $pending = Cache::get($this->cacheKey($user));

        if (! is_array($pending)) {
            throw ValidationException::withMessages([
                'otp' => ['Your verification session expired. Enter your BVN again to receive a new code.'],
            ]);
        }

        $otp = trim($otp);

        if ($otp === '' || ! preg_match('/^\d{4,8}$/', $otp)) {
            throw ValidationException::withMessages(['otp' => ['Enter the verification code from ALATPay.']]);
        }

        try {
            $verified = $this->gateway->verifyStaticAccount(new StaticAccountVerifyRequest(
                staticWalletId: (string) $pending['static_wallet_id'],
                otp: $otp,
                trackingId: (string) $pending['tracking_id'],
            ));
        } catch (AlatpayException $e) {
            $this->audit->record($user, 'bvn', $this->providerName(), 'failed', 'invalid_otp', $ipAddress);
            throw ValidationException::withMessages([
                'otp' => [$e->userFacingMessage('Invalid or expired code. Check the OTP from ALATPay and try again.')],
            ]);
        }

        $bvn = decrypt((string) $pending['bvn']);
        $dob = Carbon::parse((string) $pending['dob']);
        Cache::forget($this->cacheKey($user));

        $kyc = $this->finalizeTier2Record($user, $bvn, $dob, $ipAddress);
        $this->linkDepositAccountFromVerify($user, $verified);

        return $kyc;
    }

    private function finalizeTier2Record(User $user, string $bvn, Carbon $dob, ?string $ipAddress): UserKyc
    {
        return DB::transaction(function () use ($user, $bvn, $dob, $ipAddress): UserKyc {
            $kyc = UserKyc::query()->firstOrCreate(
                ['user_id' => $user->getKey()],
                ['tier' => KycTier::Tier1],
            )->fresh();

            if ($kyc->tier->isAtLeast(KycTier::Tier2)) {
                return $kyc;
            }

            $this->assertBvnAvailable($user, $bvn);

            $kyc->storeBvn($bvn);
            $kyc->date_of_birth = $dob;
            $kyc->tier = KycTier::Tier2;
            $kyc->save();

            $this->audit->record($user, 'bvn', $this->providerName(), 'success', null, $ipAddress, [
                'tier' => 2,
                'provider' => 'alatpay',
            ]);

            return $kyc->refresh();
        });
    }

    private function linkDepositAccountFromVerify(User $user, StaticAccountResponse $response): void
    {
        try {
            app(StaticAccountService::class)->linkFromProviderResponse($user, $response);
        } catch (\Throwable $e) {
            Log::warning('Failed to link deposit account after BVN OTP', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function linkDepositAccountFromProvision(User $user, StaticAccountProvisionResponse $response): void
    {
        if ($response->accountNumber === null || $response->staticWalletId === '') {
            return;
        }

        try {
            app(StaticAccountService::class)->linkVerifiedIndividualAccount(
                $user,
                $response->staticWalletId,
                $response->accountNumber,
                $response->accountName,
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to link recovered deposit account after BVN', [
                'user_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function assertBvnAvailable(User $user, string $bvn): void
    {
        $hash = hash('sha256', $bvn);
        $taken = UserKyc::query()
            ->where('user_id', '!=', $user->getKey())
            ->where('bvn_hash', $hash)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['bvn' => ['This BVN is already linked to another Reton account.']]);
        }
    }

    /**
     * Sandbox/demo BVNs used in tests and placeholders must never hit live ALATPay.
     */
    private function assertNotSandboxBvnOnLive(string $bvn): void
    {
        if ($this->providerName() === 'alatpay_fake') {
            return;
        }

        $sandboxBvns = [
            '22334455667',
            '11111111111',
            '00000000000',
            '12345678901',
        ];

        if (in_array($bvn, $sandboxBvns, true)) {
            throw ValidationException::withMessages([
                'bvn' => ['That looks like a demo/test BVN. Enter your real 11-digit BVN — ALATPay will SMS the phone registered to it.'],
            ]);
        }
    }

    private function defaultProvisionFailureMessage(): string
    {
        return 'ALATPay could not verify that BVN. Confirm the number is correct and matches the phone on your BVN record, then try again.';
    }

    private function providerName(): string
    {
        return config('services.alatpay.driver') === 'fake' ? 'alatpay_fake' : 'alatpay';
    }

    private function cacheKey(User $user): string
    {
        return self::CACHE_PREFIX.$user->getKey();
    }
}
