<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * AlatPay's acknowledgement of a payout request.
 */
final readonly class TransferResponse
{
    public function __construct(
        public string $providerReference,
        public string $status, // pending | completed | failed
    ) {}
}
