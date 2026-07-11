<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Kyc\Services\KycAuditService;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\StaticAccountSummary;
use App\Domain\Payments\Alatpay\Exceptions\AlatpayException;
use App\Domain\Payments\Data\ProviderContactRebindResult;
use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Models\StaticAccount;
use App\Models\User;
use App\Support\Banking\ProviderContactEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Moves ALATPay/Wema bank-alert contact emails off customer inboxes onto the
 * Reton merchant (CEO) plus-alias used for new provisions.
 */
class ProviderContactRebindService
{
    public function __construct(
        private readonly AlatpayGateway $gateway,
        private readonly KycAuditService $audit,
    ) {}

    public function rebindForUser(User $user, bool $dryRun = false, ?string $actorIp = null): ProviderContactRebindResult
    {
        $desired = ProviderContactEmail::forUser($user);

        $account = StaticAccount::query()
            ->where('user_id', $user->getKey())
            ->where('status', StaticAccountStatus::Active)
            ->where('wallet_type', StaticWalletType::Individual)
            ->whereNotNull('account_number')
            ->latest()
            ->first();

        if ($account === null) {
            return new ProviderContactRebindResult(
                status: ProviderContactRebindResult::STATUS_MISSING_ACCOUNT,
                userEmail: (string) $user->email,
                accountNumber: null,
                previousProviderEmail: null,
                desiredProviderEmail: $desired,
                message: 'No active Individual deposit account found for this user.',
            );
        }

        $provider = $this->findProviderWallet($account);

        if ($provider === null) {
            return new ProviderContactRebindResult(
                status: ProviderContactRebindResult::STATUS_NEEDS_SUPPORT,
                userEmail: (string) $user->email,
                accountNumber: $account->account_number,
                previousProviderEmail: null,
                desiredProviderEmail: $desired,
                message: 'Could not find this VA on ALATPay. Ask ALATPay support to set the contact email to '.$desired.'.',
            );
        }

        $current = strtolower(trim((string) $provider->email));

        if ($current === strtolower($desired)) {
            $this->rememberLocal($account, $desired, $current, synced: true);

            return new ProviderContactRebindResult(
                status: ProviderContactRebindResult::STATUS_ALREADY_OK,
                userEmail: (string) $user->email,
                accountNumber: $account->account_number,
                previousProviderEmail: $current !== '' ? $current : null,
                desiredProviderEmail: $desired,
                message: 'Provider contact email already points at the CEO merchant alias.',
            );
        }

        if ($dryRun) {
            return new ProviderContactRebindResult(
                status: ProviderContactRebindResult::STATUS_DRY_RUN,
                userEmail: (string) $user->email,
                accountNumber: $account->account_number,
                previousProviderEmail: $current !== '' ? $current : null,
                desiredProviderEmail: $desired,
                message: 'Would rebind provider email from '.($current !== '' ? $current : '(empty)').' → '.$desired,
            );
        }

        try {
            $this->gateway->updateStaticAccountEmail($provider->id, $desired);
        } catch (AlatpayException $e) {
            $this->rememberLocal($account, $desired, $current, synced: false);

            $this->safeAudit($user, 'failed', 'provider_email_rebind', $actorIp, [
                'account_number' => $account->account_number,
                'desired_email' => $desired,
                'previous_email' => $current,
                'error' => $e->getMessage(),
            ]);

            Log::warning('provider_contact_rebind.needs_support', [
                'user_id' => $user->getKey(),
                'account_number' => $account->account_number,
                'desired' => $desired,
                'error' => $e->getMessage(),
            ]);

            return new ProviderContactRebindResult(
                status: ProviderContactRebindResult::STATUS_NEEDS_SUPPORT,
                userEmail: (string) $user->email,
                accountNumber: $account->account_number,
                previousProviderEmail: $current !== '' ? $current : null,
                desiredProviderEmail: $desired,
                message: $e->userFacingMessage(
                    'ALATPay rejected the email update. Forward account '.$account->account_number.' and target email '.$desired.' to ALATPay support.'
                ),
            );
        }

        $this->rememberLocal($account, $desired, $current, synced: true);

        $this->safeAudit($user, 'success', null, $actorIp, [
            'action' => 'provider_email_rebind',
            'account_number' => $account->account_number,
            'desired_email' => $desired,
            'previous_email' => $current,
        ]);

        return new ProviderContactRebindResult(
            status: ProviderContactRebindResult::STATUS_REBOUND,
            userEmail: (string) $user->email,
            accountNumber: $account->account_number,
            previousProviderEmail: $current !== '' ? $current : null,
            desiredProviderEmail: $desired,
            message: 'Provider contact email rebound to the CEO merchant alias. Customer will only get Reton alerts.',
        );
    }

    /**
     * @return list<ProviderContactRebindResult>
     */
    public function rebindAll(bool $dryRun = false, ?string $actorIp = null): array
    {
        $results = [];

        $accounts = StaticAccount::query()
            ->with('user')
            ->where('status', StaticAccountStatus::Active)
            ->where('wallet_type', StaticWalletType::Individual)
            ->whereNotNull('account_number')
            ->orderBy('created_at')
            ->get();

        foreach ($accounts as $account) {
            $user = $account->user;

            if (! $user instanceof User) {
                continue;
            }

            $results[] = $this->rebindForUser($user, $dryRun, $actorIp);
        }

        return $results;
    }

    public function rebindByEmail(string $email, bool $dryRun = false, ?string $actorIp = null): ProviderContactRebindResult
    {
        $email = strtolower(trim($email));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => ["No Reton user found for {$email}."],
            ]);
        }

        return $this->rebindForUser($user, $dryRun, $actorIp);
    }

    private function findProviderWallet(StaticAccount $account): ?StaticAccountSummary
    {
        $reference = strtolower(trim((string) $account->provider_reference));
        $number = preg_replace('/\D+/', '', (string) $account->account_number) ?? '';

        for ($page = 1; $page <= 20; $page++) {
            $rows = $this->gateway->listStaticAccounts($page, 50, 1);

            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                if ($reference !== '' && strtolower($row->id) === $reference) {
                    return $row;
                }

                $rowNumber = preg_replace('/\D+/', '', (string) $row->accountNumber) ?? '';
                if ($number !== '' && $rowNumber === $number) {
                    return $row;
                }
            }

            if (count($rows) < 50) {
                break;
            }
        }

        return null;
    }

    private function rememberLocal(StaticAccount $account, string $desired, string $previous, bool $synced): void
    {
        $meta = is_array($account->metadata) ? $account->metadata : [];
        $meta['provider_email'] = $desired;
        $meta['provider_email_previous'] = $previous !== '' ? $previous : null;
        $meta['provider_email_synced'] = $synced;
        $meta['provider_email_synced_at'] = now()->toIso8601String();

        $account->forceFill(['metadata' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function safeAudit(
        User $user,
        string $status,
        ?string $failureReason,
        ?string $actorIp,
        array $meta,
    ): void {
        try {
            $this->audit->record($user, 'va_email', 'alatpay', $status, $failureReason, $actorIp, $meta);
        } catch (\Throwable $e) {
            Log::warning('provider_contact_rebind.audit_failed', [
                'user_id' => $user->getKey(),
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
