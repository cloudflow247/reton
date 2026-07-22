<?php

declare(strict_types=1);

namespace App\Domain\Recovery\Services;

use App\Domain\Fraud\Contracts\FraudScorer;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Enums\FraudRiskLevel;
use App\Domain\Recovery\Data\EligibilityResult;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Carbon;

/**
 * Decides whether a wrong-transfer recovery may freeze the receiver's funds.
 *
 * Validates the three inputs the spec calls for: the funds are still available,
 * the report is within the window, and fraud indicators. A high-risk receiver
 * extends the report window - victims get longer to claw back from flagged
 * accounts - but funds that are already gone can never be recovered.
 */
class RecoveryEligibilityEngine
{
    public function __construct(private readonly FraudScorer $fraud) {}

    public function assess(Transfer $transfer, Wallet $receiver, Money $amount): EligibilityResult
    {
        if ($receiver->availableMinor() < $amount->amount) {
            return EligibilityResult::ineligible('funds_unavailable');
        }

        if ($this->withinWindow($transfer)) {
            return EligibilityResult::eligible();
        }

        if ($this->receiverIsHighRisk($receiver, $amount)) {
            return EligibilityResult::eligible();
        }

        return EligibilityResult::ineligible('report_window_elapsed');
    }

    private function withinWindow(Transfer $transfer): bool
    {
        $reference = $transfer->completed_at ?? $transfer->created_at;
        $windowHours = (int) config('reton.recovery.report_window_hours', 48);

        return $reference !== null && ! Carbon::parse($reference)->addHours($windowHours)->isPast();
    }

    private function receiverIsHighRisk(Wallet $receiver, Money $amount): bool
    {
        $owner = User::find($receiver->owner_id);

        if (! $owner instanceof User) {
            return false;
        }

        $assessment = $this->fraud->score(new FraudContext(
            user: $owner,
            wallet: $receiver,
            amount: $amount,
            action: 'recovery',
        ));

        return $assessment->level === FraudRiskLevel::High;
    }
}
