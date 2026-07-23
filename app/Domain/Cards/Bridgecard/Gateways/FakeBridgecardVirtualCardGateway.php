<?php

declare(strict_types=1);

namespace App\Domain\Cards\Bridgecard\Gateways;

use App\Domain\Cards\Contracts\VirtualCardGateway;
use App\Domain\Cards\Data\CreateVirtualCardPayload;
use App\Domain\Cards\Data\IssuedVirtualCard;
use App\Domain\Cards\Data\VirtualCardBalance;
use App\Domain\Cards\Data\VirtualCardBillingAddress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class FakeBridgecardVirtualCardGateway implements VirtualCardGateway
{
    private const CACHE_PREFIX = 'fake_bridgecard:';

    public function ensureCardholder(CreateVirtualCardPayload $payload, ?string $existingCardholderId = null): string
    {
        if ($existingCardholderId !== null && Cache::has(self::CACHE_PREFIX.'holder:'.$existingCardholderId)) {
            return $existingCardholderId;
        }

        $id = $existingCardholderId ?? Str::lower(Str::replace('-', '', (string) Str::ulid()));
        Cache::forever(self::CACHE_PREFIX.'holder:'.$id, $payload->emailAddress);

        return $id;
    }

    public function createPrepaid(CreateVirtualCardPayload $payload, string $cardholderId): IssuedVirtualCard
    {
        $suffix = Str::padLeft((string) random_int(0, 9999), 4, '0');
        $providerCardId = Str::lower(Str::replace('-', '', (string) Str::ulid()));
        $pan = $payload->currency === 'USD'
            ? '539983000000'.$suffix
            : '506321000000'.$suffix;

        $billing = VirtualCardBillingAddress::defaultFor($payload->currency);

        $this->putCard($providerCardId, [
            'blocked' => false,
            'balance' => $payload->fundingAmountMinor,
            'currency' => $payload->currency,
            'pan' => $pan,
            'cvv' => (string) random_int(100, 999),
            'expiry' => now()->addYears(3)->format('my'),
            'billing' => $billing->toArray(),
            'brand' => 'Mastercard',
        ]);

        $card = $this->resolveCard($providerCardId);

        return new IssuedVirtualCard(
            pan: $card['pan'],
            cvv: $card['cvv'],
            cvv2: null,
            expiry: $card['expiry'],
            seqNr: '001',
            customerId: null,
            providerCardId: $providerCardId,
            providerCardholderId: $cardholderId,
            billingAddress: $billing,
            brand: 'Mastercard',
        );
    }

    public function fetchDetails(string $providerCardId): IssuedVirtualCard
    {
        $card = $this->resolveCard($providerCardId);

        return new IssuedVirtualCard(
            pan: $card['pan'],
            cvv: $card['cvv'],
            cvv2: null,
            expiry: $card['expiry'],
            seqNr: '001',
            providerCardId: $providerCardId,
            billingAddress: VirtualCardBillingAddress::fromArray($card['billing']),
            brand: $card['brand'],
        );
    }

    public function fund(string $providerCardId, int $amountMinor, string $currency, string $reference): void
    {
        $card = $this->resolveCard($providerCardId, $currency);
        $card['balance'] += $amountMinor;
        $this->putCard($providerCardId, $card);
    }

    public function block(string $providerCardId): void
    {
        $card = $this->resolveCard($providerCardId);
        $card['blocked'] = true;
        $this->putCard($providerCardId, $card);
    }

    public function unblock(string $providerCardId): void
    {
        $card = $this->resolveCard($providerCardId);
        $card['blocked'] = false;
        $this->putCard($providerCardId, $card);
    }

    public function balance(string $providerCardId): VirtualCardBalance
    {
        $card = $this->resolveCard($providerCardId);

        return new VirtualCardBalance(
            availableMinor: $card['balance'],
        );
    }

    public function ping(): bool
    {
        return true;
    }

    /**
     * @return array{blocked: bool, balance: int, currency: string, pan: string, cvv: string, expiry: string, billing: array<string, string>, brand: string}
     */
    private function resolveCard(string $providerCardId, ?string $currency = null): array
    {
        $existing = $this->getCard($providerCardId);

        if ($existing !== null) {
            return $existing;
        }

        $currency = strtoupper($currency ?? 'USD');
        $suffix = Str::padLeft(substr(preg_replace('/\D/', '', $providerCardId) ?: '0000', -4), 4, '0');

        $stub = [
            'blocked' => false,
            'balance' => 0,
            'currency' => $currency,
            'pan' => ($currency === 'USD' ? '539983000000' : '506321000000').$suffix,
            'cvv' => (string) random_int(100, 999),
            'expiry' => now()->addYears(3)->format('my'),
            'billing' => VirtualCardBillingAddress::defaultFor($currency)->toArray(),
            'brand' => 'Mastercard',
        ];

        $this->putCard($providerCardId, $stub);

        return $stub;
    }

    /** @return array{blocked: bool, balance: int, currency: string, pan: string, cvv: string, expiry: string, billing: array<string, string>, brand: string}|null */
    private function getCard(string $providerCardId): ?array
    {
        $cached = Cache::get(self::CACHE_PREFIX.'card:'.$providerCardId);

        if (! is_array($cached)) {
            return null;
        }

        if (
            ! isset($cached['blocked'], $cached['balance'], $cached['currency'], $cached['pan'], $cached['cvv'], $cached['expiry'], $cached['billing'], $cached['brand'])
            || ! is_bool($cached['blocked'])
            || ! is_int($cached['balance'])
            || ! is_string($cached['currency'])
            || ! is_string($cached['pan'])
            || ! is_string($cached['cvv'])
            || ! is_string($cached['expiry'])
            || ! is_array($cached['billing'])
            || ! is_string($cached['brand'])
        ) {
            return null;
        }

        return [
            'blocked' => $cached['blocked'],
            'balance' => $cached['balance'],
            'currency' => $cached['currency'],
            'pan' => $cached['pan'],
            'cvv' => $cached['cvv'],
            'expiry' => $cached['expiry'],
            'billing' => $cached['billing'],
            'brand' => $cached['brand'],
        ];
    }

    /** @param  array{blocked: bool, balance: int, currency: string, pan: string, cvv: string, expiry: string, billing: array<string, string>, brand: string}  $card */
    private function putCard(string $providerCardId, array $card): void
    {
        Cache::forever(self::CACHE_PREFIX.'card:'.$providerCardId, $card);
    }
}
