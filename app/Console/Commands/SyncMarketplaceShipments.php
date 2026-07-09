<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Marketplace\Enums\ShipmentStatus;
use App\Domain\Marketplace\Models\MarketplaceShipment;
use App\Domain\Marketplace\Services\ShipmentService;
use Illuminate\Console\Command;

class SyncMarketplaceShipments extends Command
{
    protected $signature = 'marketplace:sync-shipments';

    protected $description = 'Poll Giglogistics for physical order shipment updates';

    public function handle(ShipmentService $shipments): int
    {
        $updated = 0;

        MarketplaceShipment::query()
            ->whereNotIn('status', [ShipmentStatus::Delivered->value, ShipmentStatus::Failed->value])
            ->orderBy('updated_at')
            ->each(function (MarketplaceShipment $shipment) use ($shipments, &$updated): void {
                if ($shipments->syncShipment($shipment)) {
                    $updated++;
                }
            });

        $this->info("Synced {$updated} shipment(s).");

        return self::SUCCESS;
    }
}
