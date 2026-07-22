<?php

declare(strict_types=1);

namespace App\Domain\Cards\Interswitch\Gateways;

use App\Domain\Cards\Contracts\VirtualCardGateway;
use App\Domain\Cards\Data\CreateVirtualCardPayload;
use App\Domain\Cards\Data\IssuedVirtualCard;
use App\Domain\Cards\Data\VirtualCardBalance;

/**
 * @deprecated Virtual cards moved to Bridgecard - kept for reference only.
 */
final class FakeVirtualCardGateway implements VirtualCardGateway
{
    public function ensureCardholder(CreateVirtualCardPayload $payload, ?string $existingCardholderId = null): string
    {
        return $existingCardholderId ?? 'legacy-cardholder';
    }

    public function createPrepaid(CreateVirtualCardPayload $payload, string $cardholderId): IssuedVirtualCard
    {
        throw new \RuntimeException('Interswitch virtual cards are deprecated - use Bridgecard.');
    }

    public function fetchDetails(string $providerCardId): IssuedVirtualCard
    {
        throw new \RuntimeException('Interswitch virtual cards are deprecated - use Bridgecard.');
    }

    public function fund(string $providerCardId, int $amountMinor, string $currency, string $reference): void
    {
        throw new \RuntimeException('Interswitch virtual cards are deprecated - use Bridgecard.');
    }

    public function block(string $providerCardId): void {}

    public function unblock(string $providerCardId): void {}

    public function balance(string $providerCardId): VirtualCardBalance
    {
        return new VirtualCardBalance(availableMinor: 0);
    }

    public function ping(): bool
    {
        return true;
    }
}
