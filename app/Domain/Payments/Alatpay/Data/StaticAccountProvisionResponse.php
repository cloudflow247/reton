<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * AlatPay's response to a provision request. Either an OTP is pending
 * (otpTrackingId present, accountNumber null) or the account is created
 * immediately (accountNumber present).
 */
final readonly class StaticAccountProvisionResponse
{
    public function __construct(
        public string $staticWalletId,
        public ?string $otpTrackingId,
        public ?string $accountNumber = null,
        public ?string $accountName = null,
        public ?string $otpHint = null,
    ) {}
}
