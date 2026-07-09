<?php

declare(strict_types=1);

namespace App\Domain\Cards\Data;

final readonly class FxQuote
{
    public function __construct(
        public string $sourceCurrency,
        public int $sourceAmountMinor,
        public string $targetCurrency,
        public int $targetAmountMinor,
        public float $rate,
        public int $spreadBps,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source_currency' => $this->sourceCurrency,
            'source_amount_minor' => $this->sourceAmountMinor,
            'target_currency' => $this->targetCurrency,
            'target_amount_minor' => $this->targetAmountMinor,
            'rate' => $this->rate,
            'spread_bps' => $this->spreadBps,
        ];
    }
}
