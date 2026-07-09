<?php

declare(strict_types=1);

namespace App\Domain\Bills\Remita\Data;

use App\Domain\Bills\Enums\BillCategory;
use App\Support\Money\Money;

/**
 * An instruction to the provider to settle a single bill.
 *
 * `customerReference` is the biller-specific account the payment credits — a
 * phone number for airtime, a meter number for electricity, or the RRR itself.
 */
final readonly class BillPaymentInstruction
{
    public function __construct(
        public string $reference,
        public BillCategory $category,
        public string $billerCode,
        public string $customerReference,
        public Money $amount,
        public string $narration = 'Bill payment',
        public ?string $paymentCode = null,
        public ?string $customerMobile = null,
        public ?string $customerEmail = null,
        public ?string $requestReference = null,
    ) {}
}
