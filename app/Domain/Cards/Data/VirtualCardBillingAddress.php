<?php

declare(strict_types=1);

namespace App\Domain\Cards\Data;

final readonly class VirtualCardBillingAddress
{
    public function __construct(
        public string $line1,
        public string $city,
        public string $state,
        public string $postcode,
        public string $country,
        public string $countryCode,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'line1' => $this->line1,
            'city' => $this->city,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'country_code' => $this->countryCode,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            line1: (string) ($data['line1'] ?? $data['billing_address1'] ?? ''),
            city: (string) ($data['city'] ?? $data['billing_city'] ?? ''),
            state: (string) ($data['state'] ?? $data['state_code'] ?? ''),
            postcode: (string) ($data['postcode'] ?? $data['billing_zip_code'] ?? ''),
            country: (string) ($data['country'] ?? $data['billing_country'] ?? ''),
            countryCode: (string) ($data['country_code'] ?? ''),
        );
    }

    public static function defaultFor(string $currency): self
    {
        if (strtoupper($currency) === 'USD') {
            return new self(
                line1: '256 Chapman Road STE 105-4',
                city: 'Newark',
                state: 'Delaware',
                postcode: '19702',
                country: 'United States',
                countryCode: 'US',
            );
        }

        return new self(
            line1: '12 Adeola Odeku Street',
            city: 'Lagos',
            state: 'Lagos',
            postcode: '101241',
            country: 'Nigeria',
            countryCode: 'NG',
        );
    }
}
