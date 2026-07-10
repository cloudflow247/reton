<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * A transaction as reported by AlatPay, used for verification/reconciliation
 * and for enriching deposit history / statements / receipts.
 */
final readonly class RemoteTransaction
{
    public function __construct(
        public string $providerReference,
        public string $status, // completed | pending | failed
        public int $amount,     // minor units
        public string $currency,
        public ?string $narration = null,
        public ?string $payerName = null,
        public ?string $bankName = null,
        public ?string $channel = null,
        public ?string $paidAt = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Human-readable ledger / activity description.
     */
    public function fundingDescription(): string
    {
        if (filled($this->narration)) {
            return 'Bank transfer — '.$this->narration;
        }

        if (filled($this->payerName) && filled($this->bankName)) {
            return "Bank transfer from {$this->payerName} ({$this->bankName})";
        }

        if (filled($this->payerName)) {
            return 'Bank transfer from '.$this->payerName;
        }

        if (filled($this->bankName)) {
            return 'Bank transfer via '.$this->bankName;
        }

        return 'Wallet funding via bank transfer';
    }

    /**
     * @return array<string, mixed>
     */
    public function receiptMetadata(): array
    {
        return array_filter([
            'narration' => $this->narration,
            'payer_name' => $this->payerName,
            'bank_name' => $this->bankName,
            'channel' => $this->channel,
            'provider_paid_at' => $this->paidAt,
            'provider_reference' => $this->providerReference,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
