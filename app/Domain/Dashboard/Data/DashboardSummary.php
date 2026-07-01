<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Data;

/**
 * Trust-first dashboard aggregates for the authenticated customer.
 */
readonly class DashboardSummary
{
    public function __construct(
        public int $pending_callbacks,
        public int $open_recoveries,
        public int $protected_transfers_pending,
        public int $open_fraud_alerts,
        public int $trust_score,
    ) {}

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'pending_callbacks' => $this->pending_callbacks,
            'open_recoveries' => $this->open_recoveries,
            'protected_transfers_pending' => $this->protected_transfers_pending,
            'open_fraud_alerts' => $this->open_fraud_alerts,
            'trust_score' => $this->trust_score,
        ];
    }
}
