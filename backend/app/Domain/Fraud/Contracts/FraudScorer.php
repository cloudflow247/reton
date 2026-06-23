<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Contracts;

use App\Domain\Fraud\Data\FraudAssessment;
use App\Domain\Fraud\Data\FraudContext;

/**
 * Produces a risk assessment for a money movement.
 *
 * The default implementation is rule-based and in-process; this contract is the
 * seam a low-latency Go/gRPC scorer can later be bound to without touching any
 * caller.
 */
interface FraudScorer
{
    public function score(FraudContext $context): FraudAssessment;
}
