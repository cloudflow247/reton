<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * A single inbound payment into a static account, as reported by AlatPay.
 * NOTE: AlatPay reports amount in MAJOR units (e.g. 100.00 = NGN 100).
 */
final readonly class StaticAccountTransaction
{
    public function __construct(
        public string $transactionId,
        public int $status,
        public string $accountNumber,
        public float $amountMajor,
        public ?string $narration = null,
        public ?string $notificationEmail = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 1;
    }

    public function amountMinor(): int
    {
        return (int) round($this->amountMajor * 100);
    }
}
