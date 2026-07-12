<?php

declare(strict_types=1);

namespace App\Domain\Recovery\Services;

use App\Domain\Fees\Enums\FeeRail;
use App\Domain\Fees\Services\PlatformFeeService;
use App\Domain\Ledger\Data\PostingDraft;
use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Recovery\Enums\RecoveryAction;
use App\Domain\Recovery\Enums\RecoveryResolution;
use App\Domain\Recovery\Enums\RecoveryStatus;
use App\Domain\Recovery\Exceptions\CannotReportRecoveryException;
use App\Domain\Recovery\Exceptions\RecoveryAlreadyOpenException;
use App\Domain\Recovery\Exceptions\RecoveryNotOpenException;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Recovery\Models\RecoveryEvent;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Enums\TransferType;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\HeldBalanceReconciler;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Reton wrong-transfer recovery engine.
 *
 * Unlike a callback (escrowed, undelivered funds), a recovery acts on a
 * completed normal transfer whose funds already sit in the receiver's wallet.
 * Eligible recoveries soft-freeze those funds via the wallet's held balance;
 * the funds are then either clawed back to the sender (less a recovery fee) or
 * unfrozen. Every action is recorded on an immutable timeline.
 */
class RecoveryService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly SystemAccountResolver $system,
        private readonly RecoveryEligibilityEngine $eligibility,
        private readonly HeldBalanceReconciler $heldBalances,
        private readonly PlatformFeeService $fees,
    ) {}

    public function report(Transfer $transfer, User $reporter, string $reason): Recovery
    {
        if ($transfer->type !== TransferType::Normal || $transfer->status !== TransferStatus::Completed) {
            throw CannotReportRecoveryException::notNormalCompleted();
        }

        if ($this->hasOpenRecovery($transfer)) {
            throw RecoveryAlreadyOpenException::forTransfer((string) $transfer->id);
        }

        $receiver = Wallet::findOrFail($transfer->receiver_wallet_id);
        $amount = Money::of($transfer->amount, $transfer->currency);
        $verdict = $this->eligibility->assess($transfer, $receiver, $amount);

        return DB::transaction(function () use ($transfer, $reporter, $reason, $amount, $verdict): Recovery {
            $recovery = Recovery::create([
                'reference' => 'RCV-'.Str::upper((string) Str::ulid()),
                'transfer_id' => $transfer->id,
                'reported_by' => $reporter->getKey(),
                'sender_wallet_id' => $transfer->sender_wallet_id,
                'receiver_wallet_id' => $transfer->receiver_wallet_id,
                'status' => $verdict->eligible ? RecoveryStatus::Held : RecoveryStatus::Declined,
                'reason' => $reason,
                'amount' => $amount->amount,
                'currency' => $amount->currency,
                'expires_at' => $verdict->eligible
                    ? now()->addHours((int) config('reton.recovery.response_hours', 48))
                    : null,
            ]);

            $this->log($recovery, $reporter, RecoveryAction::Reported, $reason);

            if ($verdict->eligible) {
                $this->freeze($transfer->receiver_wallet_id, $amount->amount);
                $this->log($recovery, null, RecoveryAction::HeldPlaced);
            } else {
                $this->log($recovery, null, RecoveryAction::Declined, $verdict->reason);
            }

            $this->heldBalances->sync(Wallet::findOrFail($transfer->receiver_wallet_id));

            return $recovery->refresh();
        });
    }

    public function returnToSender(Recovery $recovery, ?User $resolver = null): Recovery
    {
        return DB::transaction(function () use ($recovery, $resolver): Recovery {
            $locked = Recovery::query()->whereKey($recovery->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === RecoveryStatus::Returned) {
                return $locked;
            }

            $this->assertOpen($locked);

            $amount = Money::of($locked->amount, $locked->currency);
            $fee = $this->fee($amount);
            $idempotencyKey = 'recovery-return:'.$locked->id;

            if ($this->ledger->findByIdempotencyKey($idempotencyKey) !== null) {
                $locked->update([
                    'status' => RecoveryStatus::Returned,
                    'resolution' => RecoveryResolution::Return,
                    'fee' => $fee,
                    'resolved_by' => $resolver?->getKey() ?? $locked->resolved_by,
                    'resolved_at' => $locked->resolved_at ?? now(),
                ]);

                return $locked->refresh();
            }

            $draft = PostingDraft::for(TransactionType::RecoveryReturn)
                ->describedAs('Wrong-transfer recovery returned to sender')
                ->idempotentBy($idempotencyKey)
                ->debit($this->walletAccountId($locked->receiver_wallet_id), $amount);

            if ($fee > 0) {
                $draft->credit($this->walletAccountId($locked->sender_wallet_id), Money::of($amount->amount - $fee, $locked->currency))
                    ->credit($this->system->resolve(SystemAccount::FeesRevenue, $locked->currency), Money::of($fee, $locked->currency));
            } else {
                $draft->credit($this->walletAccountId($locked->sender_wallet_id), $amount);
            }

            $this->ledger->post($draft);
            $this->unfreeze($locked->receiver_wallet_id, $amount->amount);

            $locked->update([
                'status' => RecoveryStatus::Returned,
                'resolution' => RecoveryResolution::Return,
                'fee' => $fee,
                'resolved_by' => $resolver?->getKey(),
                'resolved_at' => now(),
            ]);

            $this->heldBalances->sync(Wallet::findOrFail($locked->receiver_wallet_id));
            $this->log($locked, $resolver, RecoveryAction::Returned, null, ['fee' => $fee]);

            return $locked->refresh();
        });
    }

    public function releaseToReceiver(Recovery $recovery, ?User $resolver = null): Recovery
    {
        return DB::transaction(function () use ($recovery, $resolver): Recovery {
            $locked = Recovery::query()->whereKey($recovery->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === RecoveryStatus::Declined && $locked->resolution === RecoveryResolution::Release) {
                return $locked;
            }

            $this->assertOpen($locked);

            $this->unfreeze($locked->receiver_wallet_id, $locked->amount);

            $locked->update([
                'status' => RecoveryStatus::Declined,
                'resolution' => RecoveryResolution::Release,
                'resolved_by' => $resolver?->getKey(),
                'resolved_at' => now(),
            ]);

            $this->heldBalances->sync(Wallet::findOrFail($locked->receiver_wallet_id));
            $this->log($locked, $resolver, RecoveryAction::Released);

            return $locked->refresh();
        });
    }

    public function dispute(Recovery $recovery, User $receiver, ?string $reason = null): Recovery
    {
        if ($recovery->status !== RecoveryStatus::Held) {
            throw RecoveryNotOpenException::make((string) $recovery->id);
        }

        return DB::transaction(function () use ($recovery, $receiver, $reason): Recovery {
            $recovery->update(['status' => RecoveryStatus::Escalated]);

            $this->log($recovery, $receiver, RecoveryAction::Disputed, $reason);
            $this->log($recovery, null, RecoveryAction::Escalated);

            return $recovery->refresh();
        });
    }

    public function resolve(Recovery $recovery, RecoveryResolution $resolution, ?User $resolver): Recovery
    {
        return match ($resolution) {
            RecoveryResolution::Return => $this->returnToSender($recovery, $resolver),
            RecoveryResolution::Release => $this->releaseToReceiver($recovery, $resolver),
        };
    }

    /**
     * Escalate a recovery whose response window has elapsed unanswered.
     */
    public function expire(Recovery $recovery): Recovery
    {
        if ($recovery->status !== RecoveryStatus::Held) {
            throw RecoveryNotOpenException::make((string) $recovery->id);
        }

        return DB::transaction(function () use ($recovery): Recovery {
            $recovery->update(['status' => RecoveryStatus::Escalated]);

            $this->log($recovery, null, RecoveryAction::Expired);
            $this->log($recovery, null, RecoveryAction::Escalated);

            return $recovery->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function addEvidence(Recovery $recovery, User $actor, string $note, array $metadata = []): RecoveryEvent
    {
        $this->assertOpen($recovery);

        return $this->log($recovery, $actor, RecoveryAction::EvidenceAdded, $note, $metadata);
    }

    private function fee(Money $amount): int
    {
        return $this->fees->calculate(FeeRail::Recovery, $amount)->amount;
    }

    private function freeze(string $walletId, int $amount): void
    {
        $wallet = Wallet::query()->whereKey($walletId)->lockForUpdate()->firstOrFail();

        if ($amount > $wallet->availableMinor()) {
            throw InsufficientFundsException::for(
                (string) $walletId,
                $wallet->available(),
                Money::of($amount, $wallet->currency),
            );
        }

        Wallet::whereKey($walletId)->increment('held_balance', $amount);
    }

    private function unfreeze(string $walletId, int $amount): void
    {
        $updated = Wallet::whereKey($walletId)
            ->where('held_balance', '>=', $amount)
            ->decrement('held_balance', $amount);

        if ($updated === 0) {
            $wallet = Wallet::query()->whereKey($walletId)->firstOrFail();

            throw InsufficientFundsException::heldFor(
                (string) $walletId,
                $wallet->available(),
                Money::of($amount, $wallet->currency),
            );
        }
    }

    private function hasOpenRecovery(Transfer $transfer): bool
    {
        return Recovery::where('transfer_id', $transfer->id)
            ->whereIn('status', [RecoveryStatus::Held->value, RecoveryStatus::Escalated->value])
            ->exists();
    }

    private function assertOpen(Recovery $recovery): void
    {
        if (! $recovery->isOpen()) {
            throw RecoveryNotOpenException::make((string) $recovery->id);
        }
    }

    private function walletAccountId(string $walletId): string
    {
        return (string) Wallet::findOrFail($walletId)->ledger_account_id;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function log(
        Recovery $recovery,
        ?Model $actor,
        RecoveryAction $action,
        ?string $notes = null,
        array $metadata = [],
    ): RecoveryEvent {
        $event = new RecoveryEvent([
            'recovery_id' => $recovery->id,
            'action' => $action,
            'notes' => $notes,
            'metadata' => $metadata ?: null,
        ]);

        if ($actor !== null) {
            $event->actor()->associate($actor);
        }

        $event->created_at = now();
        $event->save();

        return $event;
    }
}
