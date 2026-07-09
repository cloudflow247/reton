<?php

declare(strict_types=1);

namespace App\Domain\Cards\Data;

final readonly class CreateVirtualCardPayload
{
    public function __construct(
        public string $pin,
        public string $firstName,
        public string $lastName,
        public string $nameOnCard,
        public string $mobileNr,
        public string $emailAddress,
        public string $city,
        public string $state,
        public string $countryCode,
        public string $cardIdentifier,
        public string $currency = 'NGN',
        public int $fundingAmountMinor = 0,
        public string $cardBrand = 'Mastercard',
        public string $cardLimit = '500000',
    ) {}

    /** @return array<string, mixed> */
    public function toRequest(): array
    {
        return [
            'pin' => $this->pin,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'nameOnCard' => $this->nameOnCard,
            'mobileNr' => $this->mobileNr,
            'emailAddress' => $this->emailAddress,
            'city' => $this->city,
            'state' => $this->state,
            'countryCode' => $this->countryCode,
            'cardIdentifier' => $this->cardIdentifier,
        ];
    }
}
