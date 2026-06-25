<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * A request to AlatPay to begin provisioning a static (permanent) account.
 */
final readonly class StaticAccountRequest
{
    public function __construct(
        public int $walletType,
        public ?string $bvn,
        public ?string $email = null,
        public string $reference = '',
    ) {}
}
