<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Contracts\FraudRule;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Data\FraudSignal;
use App\Domain\Transfers\Models\Transfer;

class VelocityRule implements FraudRule
{
    public function evaluate(FraudContext $context): ?FraudSignal
    {
        $window = (int) config('reton.fraud.velocity_window_minutes', 10);
        $max = (int) config('reton.fraud.velocity_max_count', 5);

        $recent = Transfer::where('initiated_by', $context->user->getKey())
            ->where('created_at', '>=', now()->subMinutes($window))
            ->count();

        if ($recent < $max) {
            return null;
        }

        return new FraudSignal(
            'high_velocity',
            (int) config('reton.fraud.velocity_points', 40),
            'Unusually high transaction velocity for the account.',
        );
    }
}
