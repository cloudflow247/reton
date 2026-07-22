<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Enums\DepositStatus;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Services\AlatpayDepositService;
use Illuminate\Console\Command;

/**
 * Reconciles pending deposits against AlatPay - a safety net for missed or
 * delayed webhooks. Only deposits old enough to have settled are checked.
 */
class ReconcileDeposits extends Command
{
    protected $signature = 'deposits:reconcile';

    protected $description = 'Reconcile pending AlatPay deposits against the provider';

    public function handle(AlatpayDepositService $deposits): int
    {
        $credited = 0;

        Deposit::query()
            ->where('status', DepositStatus::Pending->value)
            ->whereNotNull('provider_reference')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->orderBy('created_at')
            ->each(function (Deposit $deposit) use ($deposits, &$credited): void {
                if ($deposits->reconcile($deposit)) {
                    $credited++;
                }
            });

        $this->info("Reconciled {$credited} deposit(s).");

        return self::SUCCESS;
    }
}
