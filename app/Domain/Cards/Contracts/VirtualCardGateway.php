<?php

declare(strict_types=1);

namespace App\Domain\Cards\Contracts;

use App\Domain\Cards\Data\CreateVirtualCardPayload;
use App\Domain\Cards\Data\IssuedVirtualCard;
use App\Domain\Cards\Data\VirtualCardBalance;

interface VirtualCardGateway
{
    public function ensureCardholder(CreateVirtualCardPayload $payload, ?string $existingCardholderId = null): string;

    public function createPrepaid(CreateVirtualCardPayload $payload, string $cardholderId): IssuedVirtualCard;

    public function fetchDetails(string $providerCardId): IssuedVirtualCard;

    public function fund(string $providerCardId, int $amountMinor, string $currency, string $reference): void;

    public function block(string $providerCardId): void;

    public function unblock(string $providerCardId): void;

    public function balance(string $providerCardId): VirtualCardBalance;

    public function ping(): bool;
}
