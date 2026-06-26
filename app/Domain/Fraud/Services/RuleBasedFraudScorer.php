<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Services;

use App\Domain\Fraud\Contracts\FraudRule;
use App\Domain\Fraud\Contracts\FraudScorer;
use App\Domain\Fraud\Data\FraudAssessment;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Data\FraudSignal;
use App\Domain\Fraud\Enums\FraudAction;
use App\Domain\Fraud\Enums\FraudRiskLevel;

/**
 * In-process, rule-based fraud scorer.
 *
 * Sums the points of every triggered rule (capped at 100) and maps the total to
 * a risk level and recommended action. Implements the same contract a future
 * low-latency Go/gRPC scorer would.
 */
class RuleBasedFraudScorer implements FraudScorer
{
    /**
     * @param  iterable<FraudRule>  $rules
     */
    public function __construct(private readonly iterable $rules) {}

    public function score(FraudContext $context): FraudAssessment
    {
        $signals = [];
        $total = 0;

        foreach ($this->rules as $rule) {
            $signal = $rule->evaluate($context);

            if ($signal instanceof FraudSignal) {
                $signals[] = $signal;
                $total += $signal->points;
            }
        }

        $score = min(100, max(0, $total));

        return new FraudAssessment($score, $this->level($score), $this->action($score), $signals);
    }

    private function level(int $score): FraudRiskLevel
    {
        return match (true) {
            $score >= (int) config('reton.fraud.high_min', 70) => FraudRiskLevel::High,
            $score >= (int) config('reton.fraud.medium_min', 40) => FraudRiskLevel::Medium,
            default => FraudRiskLevel::Low,
        };
    }

    private function action(int $score): FraudAction
    {
        return match (true) {
            $score >= (int) config('reton.fraud.escalate_min', 90) => FraudAction::Escalate,
            $score >= (int) config('reton.fraud.high_min', 70) => FraudAction::Hold,
            $score >= (int) config('reton.fraud.medium_min', 40) => FraudAction::Challenge,
            default => FraudAction::Allow,
        };
    }
}
