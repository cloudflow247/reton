<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Contracts\FraudRule;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Data\FraudSignal;

class FailedPinRule implements FraudRule
{
    public function evaluate(FraudContext $context): ?FraudSignal
    {
        $threshold = (int) config('reton.fraud.failed_pin_threshold', 3);

        if ($context->user->pin_attempts < $threshold) {
            return null;
        }

        return new FraudSignal(
            'failed_pins',
            (int) config('reton.fraud.failed_pin_points', 35),
            'Multiple failed transaction-PIN attempts on the account.',
        );
    }
}
