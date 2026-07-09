<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

use App\Support\Money\Money;

/**
 * A request to AlatPay to mint a hosted payment link for a money request.
 */
final readonly class PaymentLinkRequest
{
    public function __construct(
        public string $reference,
        public Money $amount,
        public string $title,
        public string $description = '',
        public string $customerEmail = '',
        public string $customerName = '',
        public ?string $customerPhone = null,
        public ?string $redirectUrl = null,
        public ?string $expiresAt = null,
        /** ALAT Pay channel: * = all, 1 = card, 2 = transfer, 3 = bank details, 5 = USSD */
        public ?string $channel = null,
    ) {}
}
