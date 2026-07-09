<?php

declare(strict_types=1);

namespace App\Domain\Logistics\Giglogistics\Contracts;

use App\Domain\Logistics\Giglogistics\Data\CreateShipmentRequest;
use App\Domain\Logistics\Giglogistics\Data\ShipmentQuote;
use App\Domain\Logistics\Giglogistics\Data\ShipmentResponse;
use App\Domain\Logistics\Giglogistics\Data\ShipmentStatusResponse;

interface GiglogisticsGateway
{
    public function quote(CreateShipmentRequest $request): ShipmentQuote;

    public function createShipment(CreateShipmentRequest $request): ShipmentResponse;

    public function getStatus(string $externalId): ShipmentStatusResponse;
}
