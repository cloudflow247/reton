<?php

declare(strict_types=1);

namespace App\Domain\Cards\Interswitch\Gateways;

use App\Domain\Cards\Contracts\VirtualCardGateway;
use App\Domain\Cards\Data\CreateVirtualCardPayload;
use App\Domain\Cards\Data\IssuedVirtualCard;
use App\Domain\Cards\Data\VirtualCardBalance;

/**
 * @deprecated Virtual cards moved to Bridgecard — Interswitch is bills-only.
 */
final class HttpInterswitchVirtualCardGateway implements VirtualCardGateway
{
    public function ensureCardholder(CreateVirtualCardPayload $payload, ?string $existingCardholderId = null): string
    {
        throw new \RuntimeException('Interswitch virtual cards are deprecated — use Bridgecard.');
    }

    public function createPrepaid(CreateVirtualCardPayload $payload, string $cardholderId): IssuedVirtualCard
    {
        throw new \RuntimeException('Interswitch virtual cards are deprecated — use Bridgecard.');
    }

    public function fetchDetails(string $providerCardId): IssuedVirtualCard
    {
        throw new \RuntimeException('Interswitch virtual cards are deprecated — use Bridgecard.');
    }

    public function fund(string $providerCardId, int $amountMinor, string $currency, string $reference): void
    {
        throw new \RuntimeException('Interswitch virtual cards are deprecated — use Bridgecard.');
    }

    public function block(string $providerCardId): void
    {
        throw new \RuntimeException('Interswitch virtual cards are deprecated — use Bridgecard.');
    }

    public function unblock(string $providerCardId): void
    {
        throw new \RuntimeException('Interswitch virtual cards are deprecated — use Bridgecard.');
    }

    public function balance(string $providerCardId): VirtualCardBalance
    {
        throw new \RuntimeException('Interswitch virtual cards are deprecated — use Bridgecard.');
    }

    public function ping(): bool
    {
        return false;
    }
}
