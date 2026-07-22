<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Enums\PayoutStatus;
use App\Domain\Payments\Models\Payout;
use App\Domain\Payments\Services\PayoutService;
use Illuminate\Console\Command;

/**
 * Reconciles pending payouts against AlatPay - settles confirmed transfers and
 * reverses failed ones whose webhook was missed.
 */
class ReconcilePayouts extends Command
{
    protected $signature = 'payouts:reconcile';

    protected $description = 'Reconcile pending AlatPay payouts against the provider';

    public function handle(PayoutService $payouts): int
    {
        $resolved = 0;

        Payout::query()
            ->where('status', PayoutStatus::Pending->value)
            ->whereNotNull('provider_reference')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->orderBy('created_at')
            ->each(function (Payout $payout) use ($payouts, &$resolved): void {
                if ($payouts->reconcile($payout)) {
                    $resolved++;
                }
            });

        $this->info("Reconciled {$resolved} payout(s).");

        return self::SUCCESS;
    }
}
