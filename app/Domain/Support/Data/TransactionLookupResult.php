<?php

declare(strict_types=1);

namespace App\Domain\Support\Data;

/**
 * @phpstan-type SupportAction array{label: string, href: string}
 */
final readonly class TransactionLookupResult
{
    /**
     * @param  list<SupportAction>  $actions
     */
    public function __construct(
        public string $kind,
        public string $reference,
        public int $amountMinor,
        public string $currency,
        public string $status,
        public string $summary,
        public array $actions = [],
        public ?string $relatedId = null,
    ) {}
}
