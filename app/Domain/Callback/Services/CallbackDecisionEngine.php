<?php

declare(strict_types=1);

namespace App\Domain\Callback\Services;

use App\Domain\Callback\Enums\CallbackResolution;
use App\Domain\Callback\Models\Callback;
use App\Domain\Fraud\Contracts\FraudScorer;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Enums\FraudRiskLevel;
use App\Domain\Transfers\Models\Transfer;
use App\Models\User;
use App\Support\Money\Money;

/**
 * Decides the outcome of a callback for the cases that resolve automatically
 * (without an explicit admin decision).
 *
 * The fraud scorer feeds the decision: an unanswered callback is never released
 * to a high-risk receiver, regardless of the configured default.
 */
class CallbackDecisionEngine
{
    public function __construct(private readonly FraudScorer $fraud) {}

    /**
     * Outcome when a callback expires with no resolution.
     */
    public function resolveOnExpiry(Callback $callback): CallbackResolution
    {
        if ($this->receiverIsHighRisk($callback->transfer)) {
            return CallbackResolution::Refund;
        }

        return $this->configuredDefault();
    }

    private function configuredDefault(): CallbackResolution
    {
        return (string) config('reton.callback.unanswered_resolution', 'refund') === 'release'
            ? CallbackResolution::Release
            : CallbackResolution::Refund;
    }

    private function receiverIsHighRisk(?Transfer $transfer): bool
    {
        if ($transfer === null) {
            return false;
        }

        $wallet = $transfer->receiverWallet;
        $owner = $wallet !== null ? User::find($wallet->owner_id) : null;

        if ($wallet === null || ! $owner instanceof User) {
            return false;
        }

        $assessment = $this->fraud->score(new FraudContext(
            user: $owner,
            wallet: $wallet,
            amount: Money::of($transfer->amount, $transfer->currency),
            action: 'callback_expiry',
        ));

        return $assessment->level === FraudRiskLevel::High;
    }
}
