<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Kyc\Enums\KycTier;
use App\Domain\Kyc\Models\UserKyc;
use App\Domain\Kyc\Services\KycAuditService;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Support tool: free a BVN that is stuck on an abandoned / wrong account
 * so the rightful user can verify and open a deposit account.
 */
class ReleaseBvnCommand extends Command
{
    protected $signature = 'kyc:release-bvn
                            {email : Email of the account that currently holds the BVN}
                            {--force : Skip confirmation}';

    protected $description = 'Clear BVN from a user KYC record so another account can verify it';

    public function handle(KycAuditService $audit): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null) {
            $this->error("No user found for {$email}.");

            return self::FAILURE;
        }

        $kyc = UserKyc::query()->where('user_id', $user->getKey())->first();

        if ($kyc === null || $kyc->bvn_hash === null) {
            $this->warn("{$email} has no linked BVN.");

            return self::SUCCESS;
        }

        $this->table(
            ['User', 'Email', 'Tier', 'BVN last4', 'Verified at'],
            [[
                $user->name,
                $user->email,
                $kyc->tier->value,
                $kyc->bvn_last4 ?? '—',
                $kyc->bvn_verified_at?->toDateTimeString() ?? '—',
            ]],
        );

        if (! $this->option('force') && ! $this->confirm('Clear BVN from this account? They will drop to Tier 1 if currently Tier 2.')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $last4 = $kyc->bvn_last4;
        $kyc->clearBvn();

        $audit->record($user, 'bvn', 'admin', 'released', 'support_release_bvn', null, [
            'bvn_last4' => $last4,
            'tier_after' => KycTier::Tier1->value,
        ]);

        $this->info("BVN ending {$last4} released from {$email}. The rightful owner can verify again.");

        return self::SUCCESS;
    }
}
