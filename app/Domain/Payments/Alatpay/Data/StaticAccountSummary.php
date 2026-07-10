<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * A row from ALATPay Get Static Wallets.
 *
 * @see https://docs.alatpay.ng/static-wallet
 */
final readonly class StaticAccountSummary
{
    public function __construct(
        public string $id,
        public int $walletType,
        public int $status,
        public ?string $accountNumber,
        public ?string $accountName,
        public ?string $email,
    ) {}

    public function isIndividual(): bool
    {
        return $this->walletType === 1;
    }

    public function isActive(): bool
    {
        return $this->status === 1 && filled($this->accountNumber);
    }
}
