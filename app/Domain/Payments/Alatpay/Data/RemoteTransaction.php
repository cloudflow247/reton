<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * A transaction as reported by AlatPay, used for verification/reconciliation.
 */
final readonly class RemoteTransaction
{
    public function __construct(
        public string $providerReference,
        public string $status, // completed | pending | failed
        public int $amount,     // minor units
        public string $currency,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }
}
