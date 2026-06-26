<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * AlatPay's response to a payment-link request: the hosted URL the payer opens,
 * plus the provider-side reference used to reconcile the inbound payment.
 */
final readonly class PaymentLinkResponse
{
    public function __construct(
        public string $providerReference,
        public string $paymentLinkUrl,
        public ?string $expiresAt = null,
    ) {}
}
