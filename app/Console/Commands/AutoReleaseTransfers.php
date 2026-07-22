<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Callback\Models\Callback;
use App\Domain\Marketplace\Services\DigitalMarketplaceService;
use App\Domain\Transfers\Enums\HoldStatus;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Models\Hold;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Services\TransferService;
use Illuminate\Console\Command;

/**
 * Releases protected transfers whose hold window has elapsed to their receiver.
 *
 * A transfer is skipped while it has an open callback - the dispute governs the
 * funds until it is resolved.
 */
class AutoReleaseTransfers extends Command
{
    protected $signature = 'transfers:auto-release';

    protected $description = 'Release protected transfers whose hold has expired and that have no open callback';

    public function handle(TransferService $transfers, DigitalMarketplaceService $marketplace): int
    {
        $released = 0;

        Hold::query()
            ->where('status', HoldStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with('transfer')
            ->each(function (Hold $hold) use ($transfers, $marketplace, &$released): void {
                $transfer = $hold->transfer;

                if (! $transfer instanceof Transfer || $transfer->status !== TransferStatus::Held) {
                    return;
                }

                if ($marketplace->blocksAutoRelease($hold, $transfer)) {
                    return;
                }

                if ($this->hasOpenCallback($transfer)) {
                    return;
                }

                $transfers->release($transfer);
                $released++;
            });

        $this->info("Auto-released {$released} transfer(s).");

        return self::SUCCESS;
    }

    private function hasOpenCallback(Transfer $transfer): bool
    {
        return Callback::query()
            ->where('transfer_id', $transfer->id)
            ->whereIn('status', [CallbackStatus::Pending->value, CallbackStatus::Escalated->value])
            ->exists();
    }
}
