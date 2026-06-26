<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * A finalised static account: the permanent payable account number plus the
 * AlatPay-side reference used to poll transactions.
 */
final readonly class StaticAccountResponse
{
    public function __construct(
        public string $providerReference,
        public string $accountNumber,
        public ?string $accountName = null,
        public string $bankName = 'Wema Bank',
    ) {}
}
