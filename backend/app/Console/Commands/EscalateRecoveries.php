<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Recovery\Enums\RecoveryStatus;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Recovery\Services\RecoveryService;
use Illuminate\Console\Command;

/**
 * Escalates held recoveries whose receiver-response window has elapsed.
 *
 * Recovery claws back already-delivered funds, so an unanswered recovery is
 * routed to an admin rather than auto-resolved.
 */
class EscalateRecoveries extends Command
{
    protected $signature = 'recoveries:escalate';

    protected $description = 'Escalate held recoveries whose response window has elapsed';

    public function handle(RecoveryService $recoveries): int
    {
        $escalated = 0;

        Recovery::query()
            ->where('status', RecoveryStatus::Held->value)
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->each(function (Recovery $recovery) use ($recoveries, &$escalated): void {
                $recoveries->expire($recovery);
                $escalated++;
            });

        $this->info("Escalated {$escalated} recovery(ies).");

        return self::SUCCESS;
    }
}
