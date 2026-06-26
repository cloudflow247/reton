<?php

declare(strict_types=1);

namespace App\Domain\Bills\Remita\Data;

/**
 * The provider's immediate acknowledgement of a submitted bill payment. Status
 * is normalised to completed | failed | pending; pending settles later via a
 * status fetch (reconciliation).
 */
final readonly class BillProviderResult
{
    public function __construct(
        public string $providerReference,
        public string $status,
    ) {}

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
