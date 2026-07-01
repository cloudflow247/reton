<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Marketplace\Enums\DigitalOrderStatus;
use App\Domain\Marketplace\Models\DigitalOrder;
use App\Domain\Marketplace\Services\DigitalMarketplaceService;
use Illuminate\Console\Command;

/**
 * Refunds digital orders whose seller missed the delivery deadline.
 *
 * Protects buyers who never received their item — including when the seller
 * cannot respond — without requiring a manual dispute.
 */
class ExpireUndeliveredDigitalOrders extends Command
{
    protected $signature = 'marketplace:expire-undelivered';

    protected $description = 'Auto-refund digital orders past the seller delivery deadline';

    public function handle(DigitalMarketplaceService $marketplace): int
    {
        $refunded = 0;

        DigitalOrder::query()
            ->where('status', DigitalOrderStatus::PaidHeld->value)
            ->whereNotNull('delivery_deadline_at')
            ->where('delivery_deadline_at', '<=', now())
            ->orderBy('delivery_deadline_at')
            ->each(function (DigitalOrder $order) use ($marketplace, &$refunded): void {
                if ($marketplace->refundOverdueUndelivered($order)) {
                    $refunded++;
                }
            });

        $this->info("Auto-refunded {$refunded} overdue digital order(s).");

        return self::SUCCESS;
    }
}
