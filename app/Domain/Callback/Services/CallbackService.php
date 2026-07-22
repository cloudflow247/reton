<?php

declare(strict_types=1);

namespace App\Domain\Callback\Services;

use App\Domain\Callback\Enums\CallbackAction;
use App\Domain\Callback\Enums\CallbackResolution;
use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Callback\Exceptions\CallbackAlreadyOpenException;
use App\Domain\Callback\Exceptions\CallbackNotOpenException;
use App\Domain\Callback\Exceptions\CannotInitiateCallbackException;
use App\Domain\Callback\Models\Callback;
use App\Domain\Callback\Models\CallbackEvent;
use App\Domain\Fees\Enums\FeeRail;
use App\Domain\Fees\Services\PlatformFeeService;
use App\Domain\Marketplace\Services\DigitalMarketplaceService;
use App\Domain\Transfers\Enums\HoldStatus;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Broadcasting\TrustProtectionBroadcaster;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Reton Callback dispute engine.
 *
 * A callback is a sender-raised dispute over a protected transfer whose funds
 * are still escrowed. The receiver may accept (return the funds) or reject
 * (escalate to an admin/decision). Every action is recorded on an immutable
 * timeline. Money only ever moves through the already-audited
 * {@see TransferService} refund/release levers.
 */
class CallbackService
{
    public function __construct(
        private readonly TransferService $transfers,
        private readonly CallbackDecisionEngine $engine,
        private readonly DigitalMarketplaceService $marketplace,
        private readonly ProtectionFairnessService $fairness,
        private readonly PlatformFeeService $fees,
    ) {}

    public function initiate(Transfer $transfer, User $sender, string $reason): Callback
    {
        if ($transfer->type->value !== 'protected'
            || $transfer->status !== TransferStatus::Held
            || $transfer->hold?->status !== HoldStatus::Active) {
            throw CannotInitiateCallbackException::notProtectedAndHeld();
        }

        if ($this->hasOpenCallback($transfer)) {
            throw CallbackAlreadyOpenException::forTransfer((string) $transfer->id);
        }

        $this->fairness->assertCanInitiate($transfer, $sender, $reason);
        $snapshot = $this->fairness->initiationSnapshot($transfer, $sender, $reason);
        $responseHours = $snapshot->responseHours
            ?? (int) config('reton.callback.response_hours', 72);

        return DB::transaction(function () use ($transfer, $sender, $reason, $snapshot, $responseHours): Callback {
            $callback = Callback::create([
                'reference' => 'CBK-'.Str::upper((string) Str::ulid()),
                'transfer_id' => $transfer->id,
                'initiated_by' => $sender->getKey(),
                'status' => CallbackStatus::Pending,
                'reason' => $reason,
                'responds_by' => now()->addHours($responseHours),
                'metadata' => [
                    'fairness' => $snapshot->toArray(),
                ],
            ]);

            $this->log($callback, $sender, CallbackAction::Initiated, $reason, [
                'category' => $snapshot->category,
                'fairness' => $snapshot->toArray(),
            ]);

            $senderWallet = Wallet::query()->find($transfer->sender_wallet_id);
            if ($senderWallet !== null) {
                $this->fees->chargeWallet(
                    $senderWallet,
                    FeeRail::Callback,
                    Money::of((int) $transfer->amount, (string) $transfer->currency),
                    'fee:callback:'.$callback->reference,
                );
            }

            TrustProtectionBroadcaster::callbackChanged($callback, 'callback.initiated');

            $this->marketplace->markDisputed($transfer);

            return $callback;
        });
    }

    public function accept(Callback $callback, User $receiver): Callback
    {
        $this->assertOpen($callback);

        return DB::transaction(function () use ($callback, $receiver): Callback {
            $this->log($callback, $receiver, CallbackAction::Accepted);

            // The receiver agrees to return the funds: refund the sender.
            return $this->resolve($callback, CallbackResolution::Refund, $receiver);
        });
    }

    public function reject(Callback $callback, User $receiver, ?string $reason = null): Callback
    {
        $this->assertOpen($callback);

        return DB::transaction(function () use ($callback, $receiver, $reason): Callback {
            $callback->update(['status' => CallbackStatus::Escalated]);

            $this->log($callback, $receiver, CallbackAction::Rejected, $reason);
            $this->log($callback, null, CallbackAction::Escalated);

            TrustProtectionBroadcaster::callbackChanged($callback->refresh(), 'callback.escalated');

            $this->marketplace->markDisputed($callback->transfer);

            return $callback->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function addEvidence(Callback $callback, User $actor, string $note, array $metadata = []): CallbackEvent
    {
        $this->assertOpen($callback);

        $score = $this->fairness->evidenceScore($note, $metadata);
        $metadata = array_merge($metadata, [
            'evidence_score' => $score,
            'quality' => $score >= 70 ? 'strong' : ($score >= 40 ? 'fair' : 'weak'),
        ]);

        return $this->log($callback, $actor, CallbackAction::EvidenceAdded, $note, $metadata);
    }

    public function resolve(Callback $callback, CallbackResolution $resolution, ?User $resolver): Callback
    {
        $this->assertOpen($callback);

        return DB::transaction(function () use ($callback, $resolution, $resolver): Callback {
            $transfer = Transfer::findOrFail($callback->transfer_id);

            match ($resolution) {
                CallbackResolution::Refund => $this->transfers->refund($transfer, 'callback:'.$callback->reference),
                CallbackResolution::Release => $this->transfers->release($transfer, fromCallbackResolution: true),
            };

            $callback->update([
                'status' => $resolution->toStatus(),
                'resolution' => $resolution,
                'resolved_by' => $resolver?->getKey(),
                'resolved_at' => now(),
            ]);

            $this->log($callback, $resolver, CallbackAction::Resolved, null, ['resolution' => $resolution->value]);

            TrustProtectionBroadcaster::callbackChanged($callback->refresh(), 'callback.resolved');

            return $callback->refresh();
        });
    }

    public function expire(Callback $callback): Callback
    {
        $this->assertOpen($callback);

        return DB::transaction(function () use ($callback): Callback {
            $this->log($callback, null, CallbackAction::Expired);

            return $this->resolve($callback, $this->engine->resolveOnExpiry($callback), null);
        });
    }

    private function hasOpenCallback(Transfer $transfer): bool
    {
        return Callback::where('transfer_id', $transfer->id)
            ->whereIn('status', [CallbackStatus::Pending->value, CallbackStatus::Escalated->value])
            ->exists();
    }

    private function assertOpen(Callback $callback): void
    {
        if (! $callback->isOpen()) {
            throw CallbackNotOpenException::make((string) $callback->id);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function log(
        Callback $callback,
        ?Model $actor,
        CallbackAction $action,
        ?string $notes = null,
        array $metadata = [],
    ): CallbackEvent {
        $event = new CallbackEvent([
            'callback_id' => $callback->id,
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
