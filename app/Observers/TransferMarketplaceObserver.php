<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Marketplace\Services\DigitalMarketplaceService;
use App\Domain\Transfers\Models\Transfer;

class TransferMarketplaceObserver
{
    public function updated(Transfer $transfer): void
    {
        if (! $transfer->wasChanged('status')) {
            return;
        }

        app(DigitalMarketplaceService::class)->syncOrderFromTransfer($transfer);
    }
}
