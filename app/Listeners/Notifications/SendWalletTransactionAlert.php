<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Domain\Notifications\Services\TransactionAlertService;
use App\Events\Wallet\WalletFundsMoved;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWalletTransactionAlert implements ShouldQueue
{
    public function __construct(private readonly TransactionAlertService $alerts) {}

    public function handle(WalletFundsMoved $event): void
    {
        $this->alerts->handle($event);
    }
}
