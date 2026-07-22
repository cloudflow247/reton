<?php

declare(strict_types=1);

namespace App\Domain\Support\Services;

use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Callback\Models\Callback;
use App\Domain\Dashboard\Services\DashboardSummaryService;
use App\Domain\Recovery\Enums\RecoveryStatus;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Recovery\Services\RecoveryEligibilityEngine;
use App\Domain\Support\Data\SupportReply;
use App\Domain\Support\Data\TransactionLookupResult;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Enums\TransferType;
use App\Domain\Transfers\Models\Transfer;
use App\Models\User;
use App\Support\Money\Money;

/**
 * Rule-based intent router for the in-app support assistant.
 *
 * No external LLM - deterministic responses backed by live account data.
 */
class RuleBasedSupportResponder
{
    public function __construct(
        private readonly TransactionLookupService $lookup,
        private readonly DashboardSummaryService $dashboard,
        private readonly RecoveryEligibilityEngine $recoveryEligibility,
    ) {}

    public function respond(User $user, string $message): SupportReply
    {
        $text = trim($message);
        $lower = strtolower($text);

        if ($reference = $this->lookup->extractReference($text)) {
            return $this->lookupReference($user, $reference);
        }

        if ($this->matchesAny($lower, ['hello', 'hi', 'hey', 'good morning', 'good afternoon'])) {
            return $this->greeting($user);
        }

        if ($this->matchesAny($lower, ['find', 'lookup', 'search', 'track', 'transaction', 'reference', 'receipt'])) {
            return new SupportReply(
                body: 'Paste a Reton reference (e.g. TRF-01J…, DEP-01J…, CBK-01J…) and I will look it up for you. You can copy references from Activity or your transfer receipt.',
                actions: [
                    ['label' => 'Open activity', 'href' => '/activity'],
                ],
            );
        }

        if ($this->matchesAny($lower, ['callback', 'protected', 'protection', 'escrow', 'hold', 'release', 'recall'])) {
            return $this->explainProtection();
        }

        if ($this->matchesAny($lower, ['wrong transfer', 'wrong person', 'mistake', 'sent to wrong', 'recovery', 'claw back', 'accidental'])) {
            return $this->explainRecovery($user);
        }

        if ($this->matchesAny($lower, ['trust', 'score', 'fraud', 'alert', 'safety'])) {
            return $this->trustStatus($user);
        }

        if ($this->matchesAny($lower, ['callback', 'dispute']) && $this->matchesAny($lower, ['open', 'pending', 'status', 'my'])) {
            return $this->openProtectionCases($user);
        }

        if ($this->matchesAny($lower, ['human', 'agent', 'person', 'escalate', 'talk to', 'speak to', 'support team'])) {
            return new SupportReply(
                body: 'I can connect you with our support team. Tap **Talk to a human** below to open a ticket - a specialist will review your case and respond by email.',
                actions: [
                    ['label' => 'Talk to a human', 'href' => '/support?escalate=1'],
                ],
            );
        }

        if ($this->matchesAny($lower, ['fund', 'deposit', 'add money', 'top up', 'virtual account'])) {
            return new SupportReply(
                body: 'Fund your wallet by bank transfer to a one-time virtual account or your dedicated static account. Your balance updates automatically once the bank confirms payment.',
                actions: [
                    ['label' => 'Add money', 'href' => '/add-money'],
                    ['label' => 'Receive payments', 'href' => '/receive'],
                ],
            );
        }

        if ($this->matchesAny($lower, ['pin', 'transaction pin', 'locked pin'])) {
            return new SupportReply(
                body: 'Your transaction PIN authorises every payment. After repeated wrong attempts it locks temporarily. You can reset it from Profile - you will need your login password.',
                actions: [
                    ['label' => 'Set or reset PIN', 'href' => '/pin'],
                    ['label' => 'Profile', 'href' => '/profile'],
                ],
            );
        }

        return $this->helpMenu();
    }

    private function greeting(User $user): SupportReply
    {
        $name = strtok($user->name, ' ') ?: 'there';

        return new SupportReply(
            body: "Hi {$name}! I'm Reton Support - I can find transactions, explain callback protection, help with wrong-transfer recovery, and escalate to a human when needed. What can I help with?",
            actions: [
                ['label' => 'Find a transaction', 'href' => '/support?prompt=find'],
                ['label' => 'Callback protection', 'href' => '/support?prompt=protection'],
                ['label' => 'Wrong transfer', 'href' => '/support?prompt=recovery'],
            ],
        );
    }

    private function helpMenu(): SupportReply
    {
        return new SupportReply(
            body: "Here's what I can help with:\n\n• **Find a transaction** - paste a reference like TRF-… or DEP-…\n• **Callback protection** - how protected transfers and disputes work\n• **Wrong transfer** - report and recover money sent by mistake\n• **Trust score** - your protection standing and open alerts\n• **Talk to a human** - open a ticket for our team",
            actions: [
                ['label' => 'Protection center', 'href' => '/protection'],
                ['label' => 'Activity', 'href' => '/activity'],
                ['label' => 'FAQ', 'href' => '/faq'],
            ],
        );
    }

    private function explainProtection(): SupportReply
    {
        $holdHours = (int) config('reton.callback.hold_hours', 72);
        $responseHours = (int) config('reton.callback.response_hours', 72);

        return new SupportReply(
            body: "Protected transfers keep money in a hold until you confirm. The sender can **release** payment to you, or **raise a callback** to pull it back within {$holdHours} hours. If there's a dispute, the receiver has {$responseHours} hours to respond with evidence - every step is logged on your timeline.\n\nUse a protected transfer when you want a safety net before the recipient gets spendable funds.",
            actions: [
                ['label' => 'Protection center', 'href' => '/protection'],
                ['label' => 'Send protected', 'href' => '/send'],
            ],
            metadata: ['topic' => 'protection'],
        );
    }

    private function explainRecovery(User $user): SupportReply
    {
        $windowHours = (int) config('reton.recovery.report_window_hours', 48);
        $eligible = $this->latestEligibleRecoveryTransfer($user);

        $body = "Wrong-transfer recovery lets you report a normal transfer sent to the wrong person. You must report within {$windowHours} hours, and we can only hold funds still available in the receiver's wallet.";

        if ($eligible instanceof Transfer) {
            $body .= "\n\nI found a recent transfer you may be able to report: **{$eligible->reference}** ({$this->formatMoney($eligible->amount, $eligible->currency)}). Open Protection to start recovery - you'll need your PIN.";
        } else {
            $body .= "\n\nOpen Protection to see transfers eligible for recovery.";
        }

        return new SupportReply(
            body: $body,
            actions: [
                ['label' => 'Start recovery', 'href' => '/protection'],
                ['label' => 'How it works', 'href' => '/how-it-works'],
            ],
            metadata: ['topic' => 'recovery'],
        );
    }

    private function trustStatus(User $user): SupportReply
    {
        $summary = $this->dashboard->forUser($user);

        $body = sprintf(
            'Your trust score is **%d/100**. Open items: %d pending callback(s), %d recovery case(s), %d fraud alert(s), %d protected transfer(s) awaiting release.',
            $summary->trust_score,
            $summary->pending_callbacks,
            $summary->open_recoveries,
            $summary->open_fraud_alerts,
            $summary->protected_transfers_pending,
        );

        if ($summary->trust_score < 70) {
            $body .= "\n\nResolve open protection cases promptly to improve your standing.";
        }

        return new SupportReply(
            body: $body,
            actions: [
                ['label' => 'Dashboard', 'href' => '/dashboard'],
                ['label' => 'Protection center', 'href' => '/protection'],
            ],
            metadata: ['topic' => 'trust', 'trust_score' => $summary->trust_score],
        );
    }

    private function openProtectionCases(User $user): SupportReply
    {
        $walletIds = $user->wallets()->pluck('id')->all();

        if ($walletIds === []) {
            return new SupportReply(body: 'You do not have a wallet yet. Complete registration to use protection features.');
        }

        $transferScope = static function ($query) use ($walletIds): void {
            $query->whereIn('sender_wallet_id', $walletIds)
                ->orWhereIn('receiver_wallet_id', $walletIds);
        };

        $callbacks = Callback::query()
            ->whereHas('transfer', $transferScope)
            ->whereIn('status', [CallbackStatus::Pending, CallbackStatus::Escalated])
            ->with('transfer')
            ->latest()
            ->limit(3)
            ->get();

        $recoveries = Recovery::query()
            ->whereHas('transfer', $transferScope)
            ->whereIn('status', [RecoveryStatus::Held, RecoveryStatus::Escalated])
            ->with('transfer')
            ->latest()
            ->limit(3)
            ->get();

        if ($callbacks->isEmpty() && $recoveries->isEmpty()) {
            return new SupportReply(
                body: 'You have no open callbacks or recovery cases right now. Protected transfers awaiting release also appear in the Protection center.',
                actions: [
                    ['label' => 'Protection center', 'href' => '/protection'],
                ],
            );
        }

        $lines = ['Here are your open protection cases:'];

        foreach ($callbacks as $callback) {
            $lines[] = sprintf(
                '• Callback **%s** on transfer %s - %s',
                $callback->reference,
                $callback->transfer->reference,
                $callback->status->value,
            );
        }

        foreach ($recoveries as $recovery) {
            $lines[] = sprintf(
                '• Recovery **%s** on transfer %s - %s',
                $recovery->reference,
                $recovery->transfer->reference,
                $recovery->status->value,
            );
        }

        return new SupportReply(
            body: implode("\n", $lines),
            actions: [
                ['label' => 'Open protection center', 'href' => '/protection'],
            ],
        );
    }

    private function lookupReference(User $user, string $reference): SupportReply
    {
        $result = $this->lookup->lookup($user, $reference);

        if (! $result instanceof TransactionLookupResult) {
            return new SupportReply(
                body: "I couldn't find **{$reference}** on your account. Double-check the reference from your receipt or Activity - references are case-insensitive but must be exact.",
                actions: [
                    ['label' => 'View activity', 'href' => '/activity'],
                ],
            );
        }

        return new SupportReply(
            body: sprintf(
                "Found your %s:\n\n**%s**\n%s\nAmount: %s\nStatus: %s",
                $result->kind,
                $result->reference,
                $result->summary,
                $this->formatMoney($result->amountMinor, $result->currency),
                $result->status,
            ),
            actions: $result->actions,
            metadata: [
                'lookup' => [
                    'kind' => $result->kind,
                    'reference' => $result->reference,
                    'related_id' => $result->relatedId,
                ],
            ],
        );
    }

    private function latestEligibleRecoveryTransfer(User $user): ?Transfer
    {
        $walletIds = $user->wallets()->pluck('id')->all();

        if ($walletIds === []) {
            return null;
        }

        $transfers = Transfer::query()
            ->where('initiated_by', $user->id)
            ->where('type', TransferType::Normal)
            ->where('status', TransferStatus::Completed)
            ->whereIn('sender_wallet_id', $walletIds)
            ->latest('completed_at')
            ->limit(5)
            ->get();

        foreach ($transfers as $transfer) {
            $receiver = $transfer->receiverWallet;

            if ($receiver === null) {
                continue;
            }

            $amount = Money::of($transfer->amount, $transfer->currency);
            $openRecovery = Recovery::query()
                ->where('transfer_id', $transfer->id)
                ->whereIn('status', [RecoveryStatus::Held, RecoveryStatus::Escalated])
                ->exists();

            if ($openRecovery) {
                continue;
            }

            if ($this->recoveryEligibility->assess($transfer, $receiver, $amount)->eligible) {
                return $transfer;
            }
        }

        return null;
    }

    private function formatMoney(int $minor, string $currency): string
    {
        $symbol = $currency === 'USD' ? '$' : '₦';

        return $symbol.number_format($minor / 100, 2);
    }

    /**
     * @param  list<string>  $needles
     */
    private function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
