<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Data\ProviderContactRebindResult;
use App\Domain\Payments\Services\ProviderContactRebindService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Support tool: move Wema/ALATPay bank-alert emails off customer inboxes
 * onto the CEO merchant plus-alias used for new deposit accounts.
 */
class RebindProviderContactEmailCommand extends Command
{
    protected $signature = 'payments:rebind-provider-email
                            {email? : Reton user email to rebind}
                            {--all : Rebind every active Individual deposit account}
                            {--dry-run : Report what would change without calling ALATPay}
                            {--force : Skip confirmation}';

    protected $description = 'Rebind ALATPay/Wema contact emails to the Reton merchant (CEO) inbox';

    public function handle(ProviderContactRebindService $rebind): int
    {
        $email = $this->argument('email');
        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');

        if (! $all && blank($email)) {
            $this->error('Pass a user email or use --all.');

            return self::FAILURE;
        }

        if ($all && filled($email)) {
            $this->error('Use either {email} or --all, not both.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $this->option('force')) {
            $target = $all ? 'ALL active Individual deposit accounts' : (string) $email;
            if (! $this->confirm("Rebind provider contact email for {$target}?")) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        try {
            $results = $all
                ? $rebind->rebindAll($dryRun)
                : [$rebind->rebindByEmail((string) $email, $dryRun)];
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->first() ?? $e->getMessage());

            return self::FAILURE;
        }

        $rows = array_map(
            static fn (ProviderContactRebindResult $result): array => [
                $result->userEmail,
                $result->accountNumber ?? '-',
                $result->previousProviderEmail ?? '-',
                $result->desiredProviderEmail,
                $result->status,
                $result->message,
            ],
            $results,
        );

        $this->table(
            ['User', 'Account', 'Previous', 'Desired', 'Status', 'Message'],
            $rows,
        );

        $needsSupport = collect($results)->where('status', ProviderContactRebindResult::STATUS_NEEDS_SUPPORT)->count();
        $rebound = collect($results)->where('status', ProviderContactRebindResult::STATUS_REBOUND)->count();
        $ok = collect($results)->where('status', ProviderContactRebindResult::STATUS_ALREADY_OK)->count();
        $missing = collect($results)->where('status', ProviderContactRebindResult::STATUS_MISSING_ACCOUNT)->count();

        $this->info("Done. rebound={$rebound} already_ok={$ok} needs_support={$needsSupport} missing={$missing} total=".count($results));

        if ($needsSupport > 0) {
            $this->warn('ALATPay rejected API email updates for some accounts (often HTTP 404). Forward those account numbers + desired emails to ALATPay support - Reton still recorded the target CEO alias locally.');
        }

        return self::SUCCESS;
    }
}
