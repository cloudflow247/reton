<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

use App\Support\Money\Money;

/**
 * A request to AlatPay to open a collection (virtual account) for a deposit.
 */
final readonly class CollectionRequest
{
    public function __construct(
        public string $reference,
        public Money $amount,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone = null,
        public ?string $customerBvn = null,
        public string $description = 'Wallet funding',
    ) {}
}
