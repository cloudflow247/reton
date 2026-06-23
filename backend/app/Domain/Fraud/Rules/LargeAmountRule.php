<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Contracts\FraudRule;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Data\FraudSignal;

class LargeAmountRule implements FraudRule
{
    public function evaluate(FraudContext $context): ?FraudSignal
    {
        $threshold = (int) config('reton.fraud.large_amount_threshold', 5_000_000);

        if ($context->amount->amount < $threshold) {
            return null;
        }

        return new FraudSignal(
            'large_amount',
            (int) config('reton.fraud.large_amount_points', 45),
            'Transaction amount exceeds the large-transaction threshold.',
        );
    }
}
