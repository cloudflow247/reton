<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Contracts;

use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Data\FraudSignal;

/**
 * A single fraud indicator. Returns a signal when triggered, null otherwise.
 */
interface FraudRule
{
    public function evaluate(FraudContext $context): ?FraudSignal;
}
