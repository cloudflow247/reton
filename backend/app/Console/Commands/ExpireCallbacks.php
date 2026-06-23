<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Callback\Models\Callback;
use App\Domain\Callback\Services\CallbackService;
use Illuminate\Console\Command;

/**
 * Resolves callbacks whose receiver-response window has elapsed.
 *
 * Only Pending callbacks are auto-resolved; Escalated callbacks await an
 * explicit admin decision.
 */
class ExpireCallbacks extends Command
{
    protected $signature = 'callbacks:expire';

    protected $description = 'Auto-resolve callbacks whose response window has elapsed';

    public function handle(CallbackService $callbacks): int
    {
        $expired = 0;

        Callback::query()
            ->where('status', CallbackStatus::Pending->value)
            ->where('responds_by', '<=', now())
            ->orderBy('responds_by')
            ->each(function (Callback $callback) use ($callbacks, &$expired): void {
                $callbacks->expire($callback);
                $expired++;
            });

        $this->info("Expired {$expired} callback(s).");

        return self::SUCCESS;
    }
}
