<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Services;

use App\Domain\Fraud\Contracts\FraudScorer;
use App\Domain\Fraud\Data\FraudAssessment;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Models\FraudAlert;
use Illuminate\Database\Eloquent\Model;

/**
 * Application service around fraud scoring: produces an assessment and persists
 * an alert whenever a transaction is flagged.
 */
class FraudService
{
    public function __construct(private readonly FraudScorer $scorer) {}

    public function evaluate(FraudContext $context, ?Model $subject = null): FraudAssessment
    {
        $assessment = $this->scorer->score($context);

        if ($assessment->isFlagged()) {
            $this->recordAlert($context, $assessment, $subject);
        }

        return $assessment;
    }

    private function recordAlert(FraudContext $context, FraudAssessment $assessment, ?Model $subject): void
    {
        $alert = new FraudAlert([
            'user_id' => $context->user->getKey(),
            'wallet_id' => $context->wallet->getKey(),
            'action_context' => $context->action,
            'score' => $assessment->score,
            'level' => $assessment->level,
            'recommended_action' => $assessment->action,
            'signals' => $assessment->reasons(),
            'amount' => $context->amount->amount,
            'currency' => $context->amount->currency,
            'status' => 'open',
            'metadata' => array_filter([
                'ip_address' => $context->ipAddress,
                'device_fingerprint' => $context->deviceFingerprint,
            ]),
        ]);

        if ($subject !== null) {
            $alert->subject()->associate($subject);
        }

        $alert->save();
    }
}
