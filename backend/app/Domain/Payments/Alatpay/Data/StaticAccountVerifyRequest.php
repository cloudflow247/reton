<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * A request to AlatPay to validate an OTP and finalise a static account.
 */
final readonly class StaticAccountVerifyRequest
{
    public function __construct(
        public string $staticWalletId,
        public string $otp,
        public string $trackingId,
    ) {}
}
