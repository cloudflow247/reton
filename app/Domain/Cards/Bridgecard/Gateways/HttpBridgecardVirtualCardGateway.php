<?php

declare(strict_types=1);

namespace App\Domain\Cards\Bridgecard\Gateways;

use App\Domain\Cards\Bridgecard\Support\BridgecardPinCipher;
use App\Domain\Cards\Contracts\VirtualCardGateway;
use App\Domain\Cards\Data\CreateVirtualCardPayload;
use App\Domain\Cards\Data\IssuedVirtualCard;
use App\Domain\Cards\Data\VirtualCardBalance;
use App\Domain\Cards\Data\VirtualCardBillingAddress;
use App\Domain\Cards\Exceptions\VirtualCardException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class HttpBridgecardVirtualCardGateway implements VirtualCardGateway
{
    public function ensureCardholder(CreateVirtualCardPayload $payload, ?string $existingCardholderId = null): string
    {
        if ($existingCardholderId !== null) {
            return $existingCardholderId;
        }

        $response = $this->client()->post('/cardholders/create_cardholder', [
            'first_name' => $payload->firstName,
            'last_name' => $payload->lastName,
            'email' => $payload->emailAddress,
            'phone' => $payload->mobileNr,
            'address' => [
                'city' => $payload->city,
                'state' => $payload->state,
                'country' => 'Nigeria',
                'country_code' => 'NG',
            ],
            'meta_data' => [
                'card_identifier' => $payload->cardIdentifier,
            ],
        ]);

        if (! $response->successful()) {
            throw VirtualCardException::providerFailed('cardholder', $response->json('message') ?? $response->body());
        }

        $id = (string) ($response->json('data.cardholder_id') ?? $response->json('data.id') ?? '');

        if ($id === '') {
            throw VirtualCardException::providerFailed('cardholder', 'Missing cardholder ID in response.');
        }

        return $id;
    }

    public function createPrepaid(CreateVirtualCardPayload $payload, string $cardholderId): IssuedVirtualCard
    {
        $secret = (string) config('services.bridgecard.secret_key');
        $encryptedPin = BridgecardPinCipher::encrypt($payload->pin, $secret);

        $body = [
            'cardholder_id' => $cardholderId,
            'card_type' => 'virtual',
            'card_brand' => $payload->cardBrand,
            'card_currency' => $payload->currency,
            'pin' => $encryptedPin,
            'funding_amount' => (string) $payload->fundingAmountMinor,
            'meta_data' => ['card_identifier' => $payload->cardIdentifier],
        ];

        if ($payload->currency === 'USD') {
            $body['card_limit'] = $payload->cardLimit;
        }

        $response = $this->client()->post('/cards/create_card', $body);

        if (! $response->successful()) {
            throw VirtualCardException::providerFailed('create', $response->json('message') ?? $response->body());
        }

        $providerCardId = (string) ($response->json('data.card_id') ?? '');

        if ($providerCardId === '') {
            throw VirtualCardException::providerFailed('create', 'Missing card ID in response.');
        }

        return $this->fetchDetails($providerCardId);
    }

    public function fetchDetails(string $providerCardId): IssuedVirtualCard
    {
        $response = $this->client()->get('/cards/get_card_details', [
            'card_id' => $providerCardId,
        ]);

        if (! $response->successful()) {
            throw VirtualCardException::providerFailed('details', $response->json('message') ?? $response->body());
        }

        $data = $response->json('data') ?? [];
        $last4 = (string) ($data['last_4'] ?? '0000');
        $pan = str_starts_with((string) ($data['card_number'] ?? ''), 'ev:')
            ? str_repeat('*', 12).$last4
            : (string) ($data['card_number'] ?? str_repeat('*', 12).$last4);

        $expiryMonth = (string) ($data['expiry_month'] ?? '01');
        $expiryYear = (string) ($data['expiry_year'] ?? now()->addYears(3)->format('y'));
        $expiry = str_pad(substr($expiryMonth, -2), 2, '0', STR_PAD_LEFT).substr($expiryYear, -2);

        $billing = isset($data['billing_address']) && is_array($data['billing_address'])
            ? VirtualCardBillingAddress::fromArray($data['billing_address'])
            : VirtualCardBillingAddress::defaultFor((string) ($data['card_currency'] ?? 'USD'));

        return new IssuedVirtualCard(
            pan: $pan,
            cvv: (string) ($data['cvv'] ?? '***'),
            cvv2: null,
            expiry: $expiry,
            seqNr: '001',
            customerId: (string) ($data['cardholder_id'] ?? null),
            providerCardId: $providerCardId,
            providerCardholderId: (string) ($data['cardholder_id'] ?? null),
            billingAddress: $billing,
            brand: (string) ($data['brand'] ?? 'Mastercard'),
        );
    }

    public function fund(string $providerCardId, int $amountMinor, string $currency, string $reference): void
    {
        $response = $this->client()->patch('/cards/fund_card_asynchronously', [
            'card_id' => $providerCardId,
            'amount' => (string) $amountMinor,
            'transaction_reference' => $reference,
            'currency' => strtoupper($currency),
        ]);

        if (! $response->successful() && $response->status() !== 202) {
            throw VirtualCardException::providerFailed('fund', $response->json('message') ?? $response->body());
        }
    }

    public function block(string $providerCardId): void
    {
        $response = $this->client()->patch('/cards/freeze_card?card_id='.$providerCardId);

        if (! $response->successful()) {
            throw VirtualCardException::providerFailed('freeze', $response->json('message') ?? $response->body());
        }
    }

    public function unblock(string $providerCardId): void
    {
        $response = $this->client()->patch('/cards/unfreeze_card?card_id='.$providerCardId);

        if (! $response->successful()) {
            throw VirtualCardException::providerFailed('unfreeze', $response->json('message') ?? $response->body());
        }
    }

    public function balance(string $providerCardId): VirtualCardBalance
    {
        $response = $this->client()->get('/cards/get_card_balance', [
            'card_id' => $providerCardId,
        ]);

        if (! $response->successful()) {
            throw VirtualCardException::providerFailed('balance', $response->json('message') ?? $response->body());
        }

        $data = $response->json('data') ?? [];

        return new VirtualCardBalance(
            availableMinor: (int) ($data['available_balance'] ?? $data['balance'] ?? 0),
        );
    }

    public function ping(): bool
    {
        $token = (string) config('services.bridgecard.access_token');

        return $token !== '';
    }

    private function client(): PendingRequest
    {
        $token = (string) config('services.bridgecard.access_token');
        $timeout = (int) config('services.bridgecard.timeout', 20);
        $base = rtrim((string) config('services.bridgecard.base_url'), '/');

        return Http::baseUrl($base)
            ->timeout($timeout)
            ->acceptJson()
            ->withHeaders([
                'token' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ])
            ->throw(false);
    }
}
