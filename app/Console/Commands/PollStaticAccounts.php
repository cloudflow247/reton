<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Payments\Services\StaticAccountService;
use Illuminate\Console\Command;

/**
 * Polls active AlatPay static accounts for new inbound payments and credits the
 * owning wallets. This is the primary funding path for static accounts (AlatPay
 * exposes a transactions endpoint rather than a webhook for these).
 */
class PollStaticAccounts extends Command
{
    protected $signature = 'static-accounts:poll';

    protected $description = 'Poll active AlatPay static accounts and credit new inbound payments';

    public function handle(StaticAccountService $accounts): int
    {
        $credited = 0;

        StaticAccount::query()
            ->where('status', StaticAccountStatus::Active->value)
            ->whereNotNull('account_number')
            ->orderBy('last_polled_at')
            ->each(function (StaticAccount $account) use ($accounts, &$credited): void {
                $credited += $accounts->poll($account);
            });

        $this->info("Credited {$credited} static-account payment(s).");

        return self::SUCCESS;
    }
}
