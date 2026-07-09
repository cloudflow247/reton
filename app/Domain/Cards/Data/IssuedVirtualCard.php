<?php

declare(strict_types=1);

namespace App\Domain\Cards\Data;

final readonly class IssuedVirtualCard
{
    public function __construct(
        public string $pan,
        public string $cvv,
        public ?string $cvv2,
        public string $expiry,
        public string $seqNr,
        public ?string $customerId = null,
        public ?string $providerCardId = null,
        public ?string $providerCardholderId = null,
        public ?VirtualCardBillingAddress $billingAddress = null,
        public string $brand = 'Mastercard',
    ) {}
}
