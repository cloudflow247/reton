<?php

declare(strict_types=1);

namespace App\Domain\Callback\Services;

use App\Domain\Callback\Data\FairnessAssessment;
use App\Domain\Callback\Enums\CallbackAction;
use App\Domain\Callback\Enums\CallbackResolution;
use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Callback\Exceptions\CannotInitiateCallbackException;
use App\Domain\Callback\Models\Callback;
use App\Domain\Fraud\Contracts\FraudScorer;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Enums\FraudRiskLevel;
use App\Domain\Transfers\Enums\TransferType;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Str;

/**
 * Fair-usage engine for P2P Callback Protection.
 *
 * Scores both parties, classifies dispute reasons, gates abusive initiates,
 * and produces explainable expiry resolutions - without putting an opaque
 * model in charge of money movement.
 */
class ProtectionFairnessService
{
    public function __construct(
        private readonly FraudScorer $fraud,
    ) {}

    public function scoreUser(User $user): int
    {
        $initiated = Callback::query()->where('initiated_by', $user->getKey())->count();
        $refundWins = Callback::query()
            ->where('initiated_by', $user->getKey())
            ->where('status', CallbackStatus::Refunded)
            ->count();
        $ghosted = Callback::query()
            ->where('initiated_by', $user->getKey())
            ->where('status', CallbackStatus::Refunded)
            ->whereNull('resolved_by')
            ->whereHas('events', fn ($q) => $q->where('action', CallbackAction::Expired))
            ->count();

        $received = Callback::query()
            ->whereHas('transfer.receiverWallet', fn ($q) => $q->where('owner_id', $user->getKey()))
            ->count();
        $accepted = Callback::query()
            ->whereHas('transfer.receiverWallet', fn ($q) => $q->where('owner_id', $user->getKey()))
            ->where('status', CallbackStatus::Refunded)
            ->where('resolved_by', $user->getKey())
            ->count();

        $score = 78;

        if ($initiated > 0) {
            $winRate = $refundWins / max(1, $initiated);
            $score -= (int) round(min(28, $winRate * 28));
            $score -= min(20, $ghosted * 6);
            $score -= min(15, max(0, $initiated - 2) * 3);
        }

        if ($received > 0) {
            $acceptRate = $accepted / max(1, $received);
            $score += (int) round(min(12, $acceptRate * 12));
            $ignored = $received - $accepted;
            $score -= min(18, max(0, $ignored) * 4);
        }

        $protected = Transfer::query()
            ->where('initiated_by', $user->getKey())
            ->where('type', TransferType::Protected)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $callbacksFromProtected = Callback::query()
            ->where('initiated_by', $user->getKey())
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($protected >= 3) {
            $conversion = $callbacksFromProtected / max(1, $protected);
            if ($conversion >= 0.6) {
                $score -= (int) round(min(25, ($conversion - 0.5) * 50));
            }
        }

        return max(0, min(100, $score));
    }

    public function classifyReason(string $reason): string
    {
        $text = Str::lower(trim($reason));

        return match (true) {
            $text === '' => 'unspecified',
            Str::contains($text, ['wrong person', 'wrong account', 'wrong number', 'mistaken', 'accident', 'sent to']) => 'wrong_recipient',
            Str::contains($text, ['not deliver', 'never deliver', 'never got', 'did not receive', "didn't receive", 'no goods', 'not sent']) => 'not_delivered',
            Str::contains($text, ['scam', 'fraud', 'fake', 'impersonat']) => 'suspected_fraud',
            Str::contains($text, ['duplicate', 'paid twice', 'double']) => 'duplicate_payment',
            Str::contains($text, ['amount', 'overpaid', 'too much', 'wrong amount']) => 'wrong_amount',
            Str::contains($text, ['service', 'not as', 'quality', 'broken']) => 'service_issue',
            default => 'other',
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function evidenceScore(string $note, array $metadata = []): int
    {
        $score = 20;
        $length = Str::length(trim($note));

        if ($length >= 20) {
            $score += 25;
        } elseif ($length >= 8) {
            $score += 12;
        }

        $url = $metadata['url'] ?? $metadata['evidence_url'] ?? null;
        if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
            $score += 30;
            if (Str::contains($host, ['bit.ly', 'tinyurl', 't.co', 'goo.gl'])) {
                $score -= 20;
            }
        }

        if (Str::contains(Str::lower($note), ['http://', 'https://'])) {
            $score += 10;
        }

        return max(0, min(100, $score));
    }

    public function holdHoursFor(User $sender, User $receiver, Money $amount): int
    {
        $base = (int) config('reton.callback.hold_hours', 72);
        $min = (int) config('reton.callback.fairness.hold_hours_min', 24);
        $max = (int) config('reton.callback.fairness.hold_hours_max', 120);
        $large = (int) config('reton.callback.fairness.large_amount_minor', 500_000);

        $hours = $base;
        $senderScore = $this->scoreUser($sender);
        $receiverScore = $this->scoreUser($receiver);

        if ($amount->amount >= $large) {
            $hours += 24;
        }

        if ($senderScore >= 80 && $receiverScore >= 80) {
            $hours -= 12;
        }

        if ($receiverScore < 45) {
            $hours += 12;
        }

        return max($min, min($max, $hours));
    }

    public function responseHoursFor(User $sender, User $receiver): int
    {
        $base = (int) config('reton.callback.response_hours', 24);
        $min = (int) config('reton.callback.fairness.response_hours_min', 12);
        $max = (int) config('reton.callback.fairness.response_hours_max', 48);

        $hours = $base;
        $receiverScore = $this->scoreUser($receiver);

        if ($receiverScore >= 80) {
            $hours += 12;
        } elseif ($receiverScore < 45) {
            $hours -= 6;
        }

        if ($this->userIsHighRisk($receiver, null, 'callback_window')) {
            $hours = min($hours, $min + 6);
        }

        if ($this->scoreUser($sender) < 40) {
            $hours = min($hours, $base);
        }

        return max($min, min($max, $hours));
    }

    public function assertCanInitiate(Transfer $transfer, User $sender, string $reason): void
    {
        $maxOpen = (int) config('reton.callback.fairness.max_open_callbacks', 3);
        $maxWeek = (int) config('reton.callback.fairness.max_callbacks_per_week', 5);
        $minReason = (int) config('reton.callback.fairness.min_reason_length', 8);

        if (Str::length(trim($reason)) < $minReason) {
            throw CannotInitiateCallbackException::reasonTooShort($minReason);
        }

        $open = Callback::query()
            ->where('initiated_by', $sender->getKey())
            ->whereIn('status', [CallbackStatus::Pending, CallbackStatus::Escalated])
            ->count();

        if ($open >= $maxOpen) {
            throw CannotInitiateCallbackException::tooManyOpen($maxOpen);
        }

        $week = Callback::query()
            ->where('initiated_by', $sender->getKey())
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($week >= $maxWeek) {
            throw CannotInitiateCallbackException::rateLimited($maxWeek);
        }

        $protected = Transfer::query()
            ->where('initiated_by', $sender->getKey())
            ->where('type', TransferType::Protected)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $callbacks = Callback::query()
            ->where('initiated_by', $sender->getKey())
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $maxConversion = (float) config('reton.callback.fairness.max_protected_conversion', 0.7);

        if ($protected >= 5 && ($callbacks / max(1, $protected)) >= $maxConversion) {
            throw CannotInitiateCallbackException::abuseSuspected();
        }

        if ($this->scoreUser($sender) < (int) config('reton.callback.fairness.min_sender_score', 25)) {
            throw CannotInitiateCallbackException::abuseSuspected();
        }
    }

    public function assessExpiry(Callback $callback): FairnessAssessment
    {
        $transfer = $callback->transfer;
        $sender = $this->senderOf($transfer);
        $receiver = $this->receiverOf($transfer);

        $senderScore = $sender instanceof User ? $this->scoreUser($sender) : 50;
        $receiverScore = $receiver instanceof User ? $this->scoreUser($receiver) : 50;
        $category = $this->classifyReason((string) ($callback->reason ?? ''));
        $evidence = $this->latestEvidenceScore($callback);
        $reasons = [];

        $senderHigh = $sender instanceof User && $this->userIsHighRisk($sender, $transfer, 'callback_expiry');
        $receiverHigh = $receiver instanceof User && $this->userIsHighRisk($receiver, $transfer, 'callback_expiry');

        if ($receiverHigh) {
            $reasons[] = 'Receiver scored high-risk - funds returned to sender.';

            return new FairnessAssessment(
                senderScore: $senderScore,
                receiverScore: $receiverScore,
                category: $category,
                resolution: CallbackResolution::Refund,
                reasons: $reasons,
                evidenceScore: $evidence,
            );
        }

        if ($senderHigh && $receiverScore >= 60) {
            $reasons[] = 'Sender scored high-risk while receiver trust is healthy - funds released.';

            return new FairnessAssessment(
                senderScore: $senderScore,
                receiverScore: $receiverScore,
                category: $category,
                resolution: CallbackResolution::Release,
                reasons: $reasons,
                evidenceScore: $evidence,
            );
        }

        $delta = $receiverScore - $senderScore;

        if ($delta >= 20) {
            $reasons[] = 'Receiver fairness score is meaningfully higher than sender.';
            $resolution = CallbackResolution::Release;
        } elseif ($delta <= -20) {
            $reasons[] = 'Sender fairness score is meaningfully higher than receiver.';
            $resolution = CallbackResolution::Refund;
        } else {
            $resolution = $this->configuredDefault();
            $reasons[] = $resolution === CallbackResolution::Refund
                ? 'Scores are close - default protects the party who raised the callback.'
                : 'Scores are close - default releases held funds to the receiver.';
        }

        if ($category === 'suspected_fraud' && $resolution === CallbackResolution::Release && $senderScore >= 50) {
            $resolution = CallbackResolution::Refund;
            $reasons[] = 'Suspected-fraud category tipped the decision toward refund.';
        }

        if ($evidence !== null && $evidence >= 70 && $resolution === CallbackResolution::Release && $senderScore >= 55) {
            $resolution = CallbackResolution::Refund;
            $reasons[] = 'Strong evidence package supported the sender on expiry.';
        }

        if ($evidence !== null && $evidence < 25 && $resolution === CallbackResolution::Refund && $receiverScore >= 70 && ! $senderHigh) {
            $resolution = CallbackResolution::Release;
            $reasons[] = 'Weak evidence with a strong receiver tipped toward release.';
        }

        return new FairnessAssessment(
            senderScore: $senderScore,
            receiverScore: $receiverScore,
            category: $category,
            resolution: $resolution,
            reasons: $reasons,
            evidenceScore: $evidence,
        );
    }

    public function initiationSnapshot(Transfer $transfer, User $sender, string $reason): FairnessAssessment
    {
        $receiver = $this->receiverOf($transfer);
        $senderScore = $this->scoreUser($sender);
        $receiverScore = $receiver instanceof User ? $this->scoreUser($receiver) : 50;
        $category = $this->classifyReason($reason);
        $responseHours = $receiver instanceof User
            ? $this->responseHoursFor($sender, $receiver)
            : (int) config('reton.callback.response_hours', 24);

        return new FairnessAssessment(
            senderScore: $senderScore,
            receiverScore: $receiverScore,
            category: $category,
            resolution: CallbackResolution::Refund,
            reasons: [
                'Callback opened under fair-usage review.',
                'Dispute category: '.$category.'.',
            ],
            responseHours: $responseHours,
        );
    }

    private function configuredDefault(): CallbackResolution
    {
        return (string) config('reton.callback.unanswered_resolution', 'refund') === 'release'
            ? CallbackResolution::Release
            : CallbackResolution::Refund;
    }

    private function latestEvidenceScore(Callback $callback): ?int
    {
        $event = $callback->events()
            ->where('action', CallbackAction::EvidenceAdded)
            ->latest('created_at')
            ->first();

        if ($event === null) {
            return null;
        }

        return $this->evidenceScore((string) ($event->notes ?? ''), (array) ($event->metadata ?? []));
    }

    private function senderOf(?Transfer $transfer): ?User
    {
        if ($transfer === null) {
            return null;
        }

        $wallet = $transfer->senderWallet;

        return $wallet instanceof Wallet ? User::find($wallet->owner_id) : null;
    }

    private function receiverOf(?Transfer $transfer): ?User
    {
        if ($transfer === null) {
            return null;
        }

        $wallet = $transfer->receiverWallet;

        return $wallet instanceof Wallet ? User::find($wallet->owner_id) : null;
    }

    private function userIsHighRisk(User $user, ?Transfer $transfer, string $action): bool
    {
        $wallet = $user->wallets()->first();
        $amount = $transfer !== null
            ? Money::of($transfer->amount, $transfer->currency)
            : Money::of(0, 'NGN');

        $assessment = $this->fraud->score(new FraudContext(
            user: $user,
            wallet: $wallet,
            amount: $amount,
            action: $action,
        ));

        return $assessment->level === FraudRiskLevel::High;
    }
}
