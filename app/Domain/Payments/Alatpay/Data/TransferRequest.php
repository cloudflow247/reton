<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

use App\Support\Money\Money;

/**
 * A request to AlatPay to pay out funds to an external bank account.
 */
final readonly class TransferRequest
{
    public function __construct(
        public string $reference,
        public Money $amount,
        public string $bankCode,
        public string $accountNumber,
        public string $accountName,
        public string $narration = 'Wallet withdrawal',
    ) {}
}
