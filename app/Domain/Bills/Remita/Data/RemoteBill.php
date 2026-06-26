<?php

declare(strict_types=1);

namespace App\Domain\Bills\Remita\Data;

/**
 * The provider's current view of a previously-submitted bill, used to reconcile
 * payments left pending at submission time.
 */
final readonly class RemoteBill
{
    public function __construct(
        public string $providerReference,
        public string $status,
        public int $amount,
        public string $currency,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }
}
