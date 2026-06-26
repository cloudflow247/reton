<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * AlatPay's response to a collection request: the virtual account the customer
 * pays into, plus the provider-side reference used to reconcile.
 */
final readonly class CollectionResponse
{
    public function __construct(
        public string $providerReference,
        public string $accountNumber,
        public string $bankName,
        public string $accountName,
        public ?string $expiresAt = null,
    ) {}

    /** @return array<string, string|null> */
    public function virtualAccount(): array
    {
        return [
            'account_number' => $this->accountNumber,
            'bank_name' => $this->bankName,
            'account_name' => $this->accountName,
            'expires_at' => $this->expiresAt,
        ];
    }
}
