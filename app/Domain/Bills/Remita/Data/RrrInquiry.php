<?php

declare(strict_types=1);

namespace App\Domain\Bills\Remita\Data;

use App\Support\Money\Money;

/**
 * The result of resolving a Remita Retrieval Reference (RRR) to the bill it
 * represents: who is owed, how much, and whether it is still outstanding.
 */
final readonly class RrrInquiry
{
    public function __construct(
        public string $rrr,
        public string $billerName,
        public Money $amount,
        public string $payerName,
        public bool $isPaid,
    ) {}
}
