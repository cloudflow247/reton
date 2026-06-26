<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Services;

use App\Domain\Ledger\Data\PostingDraft;
use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Transfers\Enums\HoldStatus;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Enums\TransferType;
use App\Domain\Transfers\Exceptions\InvalidTransferStateException;
use App\Domain\Transfers\Models\Hold;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates customer transfers and the escrow lifecycle of protected
 * (callback) transfers. All money movement is delegated to the LedgerService;
 * this service records the user-facing Transfer/Hold and keeps them in step
 * with the underlying postings.
 */
class TransferService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly SystemAccountResolver $system,
        private readonly WalletService $wallets,
    ) {}

    /**
     * A normal transfer settles immediately: funds move sender -> receiver.
     */
    public function sendNormal(
        User $sender,
        Wallet $from,
        Wallet $to,
        Money $amount,
        ?string $note = null,
        ?string $idempotencyKey = null,
    ): Transfer {
        if (($replay = $this->replay($idempotencyKey)) !== null) {
            return $replay;
        }

        return DB::transaction(function () use ($sender, $from, $to, $amount, $note, $idempotencyKey): Transfer {
            $transaction = $this->wallets->transfer($from, $to, $amount, $idempotencyKey, [
                'channel' => 'transfer',
            ]);

            return $this->record(
                $sender,
                $from,
                $to,
                $amount,
                TransferType::Normal,
                TransferStatus::Completed,
                $transaction,
                $note,
                $idempotencyKey,
                completed: true,
            );
        });
    }

    /**
     * A protected transfer parks funds in escrow under a hold; the receiver is
     * not credited until the transfer is released.
     */
    public function sendProtected(
        User $sender,
        Wallet $from,
        Wallet $to,
        Money $amount,
        ?string $note = null,
        ?string $idempotencyKey = null,
    ): Transfer {
        if (($replay = $this->replay($idempotencyKey)) !== null) {
            return $replay;
        }

        return DB::transaction(function () use ($sender, $from, $to, $amount, $note, $idempotencyKey): Transfer {
            $this->wallets->ensureCanSpend($from, $amount);

            $escrow = $this->system->resolve(SystemAccount::CallbackHolds, $from->currency);

            $transaction = $this->ledger->post(
                PostingDraft::for(TransactionType::CallbackHold)
                    ->describedAs('Protected transfer — funds held')
                    ->idempotentBy($idempotencyKey)
                    ->initiatedBy($sender)
                    ->debit($from->ledger_account_id, $amount)
                    ->credit($escrow, $amount)
            );

            $transfer = $this->record(
                $sender,
                $from,
                $to,
                $amount,
                TransferType::Protected,
                TransferStatus::Held,
                $transaction,
                $note,
                $idempotencyKey,
                completed: false,
            );

            Hold::create([
                'transfer_id' => $transfer->id,
                'amount' => $amount->amount,
                'currency' => $amount->currency,
                'status' => HoldStatus::Active,
                'expires_at' => now()->addHours((int) config('reton.callback.hold_hours', 72)),
            ]);

            return $transfer->load('hold');
        });
    }

    /**
     * Release an escrowed protected transfer to its receiver.
     */
    public function release(Transfer $transfer): Transfer
    {
        return DB::transaction(function () use ($transfer): Transfer {
            $hold = $this->lockActiveHold($transfer)
                ?? throw InvalidTransferStateException::notReleasable((string) $transfer->id);

            $escrow = $this->system->resolve(SystemAccount::CallbackHolds, $transfer->currency);

            $this->ledger->post(
                PostingDraft::for(TransactionType::CallbackRelease)
                    ->describedAs('Protected transfer released to receiver')
                    ->debit($escrow, $this->holdMoney($transfer))
                    ->credit($this->walletAccountId($transfer->receiver_wallet_id), $this->holdMoney($transfer))
            );

            $hold->update(['status' => HoldStatus::Released, 'resolved_at' => now()]);
            $transfer->update(['status' => TransferStatus::Completed, 'completed_at' => now()]);

            return $transfer->refresh()->load('hold');
        });
    }

    /**
     * Refund an escrowed protected transfer back to its sender.
     */
    public function refund(Transfer $transfer, ?string $reason = null): Transfer
    {
        return DB::transaction(function () use ($transfer, $reason): Transfer {
            $hold = $this->lockActiveHold($transfer)
                ?? throw InvalidTransferStateException::notRefundable((string) $transfer->id);

            $escrow = $this->system->resolve(SystemAccount::CallbackHolds, $transfer->currency);

            $this->ledger->post(
                PostingDraft::for(TransactionType::CallbackRefund)
                    ->describedAs('Protected transfer refunded to sender')
                    ->debit($escrow, $this->holdMoney($transfer))
                    ->credit($this->walletAccountId($transfer->sender_wallet_id), $this->holdMoney($transfer))
            );

            $hold->update(['status' => HoldStatus::Refunded, 'reason' => $reason, 'resolved_at' => now()]);
            $transfer->update(['status' => TransferStatus::Refunded, 'completed_at' => now()]);

            return $transfer->refresh()->load('hold');
        });
    }

    private function lockActiveHold(Transfer $transfer): ?Hold
    {
        if (! $transfer->isProtected()) {
            return null;
        }

        $hold = Hold::where('transfer_id', $transfer->id)
            ->where('status', HoldStatus::Active)
            ->lockForUpdate()
            ->first();

        return $hold instanceof Hold ? $hold : null;
    }

    private function holdMoney(Transfer $transfer): Money
    {
        return Money::of($transfer->amount, $transfer->currency);
    }

    private function walletAccountId(string $walletId): string
    {
        return (string) Wallet::findOrFail($walletId)->ledger_account_id;
    }

    private function replay(?string $idempotencyKey): ?Transfer
    {
        $transaction = $this->ledger->findByIdempotencyKey($idempotencyKey);

        if ($transaction === null) {
            return null;
        }

        $transfer = Transfer::where('transaction_id', $transaction->id)->first();

        return $transfer instanceof Transfer ? $transfer->load('hold') : null;
    }

    private function record(
        User $sender,
        Wallet $from,
        Wallet $to,
        Money $amount,
        TransferType $type,
        TransferStatus $status,
        Transaction $transaction,
        ?string $note,
        ?string $idempotencyKey,
        bool $completed,
    ): Transfer {
        return Transfer::create([
            'reference' => 'TRF-'.Str::upper((string) Str::ulid()),
            'sender_wallet_id' => $from->id,
            'receiver_wallet_id' => $to->id,
            'initiated_by' => $sender->getKey(),
            'type' => $type,
            'status' => $status,
            'currency' => $amount->currency,
            'amount' => $amount->amount,
            'note' => $note,
            'transaction_id' => $transaction->id,
            'idempotency_key' => $idempotencyKey,
            'completed_at' => $completed ? now() : null,
        ]);
    }
}
