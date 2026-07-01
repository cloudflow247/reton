<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Ledger\Data\PostingDraft;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Exceptions\WalletCurrencyMismatchException;
use App\Domain\Wallet\Models\Wallet;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Use-case service for customer wallets.
 *
 * Every money movement is delegated to the {@see LedgerService}; this service
 * owns wallet lifecycle, the system-account counterparties to book against, and
 * the available-balance guardrails customers experience.
 */
class WalletService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly SystemAccountResolver $system,
    ) {}

    /**
     * Open (or return the existing) wallet for an owner in a given currency.
     */
    public function open(Model $owner, string $currency = 'NGN'): Wallet
    {
        $currency = Money::zero($currency)->currency;

        return DB::transaction(function () use ($owner, $currency): Wallet {
            $existing = Wallet::where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey())
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $account = new LedgerAccount([
                'code' => 'wallet:'.Str::lower((string) Str::ulid()).':'.$currency,
                'name' => 'Wallet '.$currency.' — '.class_basename($owner).' '.$owner->getKey(),
                'type' => AccountType::Liability,
                'currency' => $currency,
                'is_system' => false,
            ]);
            $account->owner()->associate($owner);
            $account->save();

            $wallet = new Wallet([
                'ledger_account_id' => $account->getKey(),
                'currency' => $currency,
                'status' => 'active',
            ]);
            $wallet->owner()->associate($owner);
            $wallet->save();

            // Pull the database-applied defaults (balance, held_balance) so the
            // returned model reflects persisted state.
            return $wallet->refresh();
        });
    }

    /**
     * Credit a wallet from house cash (e.g. a confirmed deposit).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function fund(Wallet $wallet, Money $amount, ?string $idempotencyKey = null, array $metadata = []): Transaction
    {
        $this->assertCurrency($wallet, $amount);

        $cash = $this->system->resolve(SystemAccount::Cash, $wallet->currency);

        return $this->ledger->post(
            PostingDraft::for(TransactionType::WalletFunding)
                ->describedAs('Wallet funding')
                ->idempotentBy($idempotencyKey)
                ->withMetadata($metadata + ['wallet_id' => $wallet->getKey()])
                ->debit($cash, $amount)
                ->credit($wallet->ledger_account_id, $amount)
        );
    }

    /**
     * Debit a wallet towards an external settlement (e.g. a bank payout).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withdraw(Wallet $wallet, Money $amount, ?string $idempotencyKey = null, array $metadata = []): Transaction
    {
        $this->assertCurrency($wallet, $amount);

        if (($replay = $this->ledger->findByIdempotencyKey($idempotencyKey)) !== null) {
            return $replay;
        }

        return DB::transaction(function () use ($wallet, $amount, $idempotencyKey, $metadata): Transaction {
            $this->assertSufficientFunds($wallet, $amount);

            $settlement = $this->system->resolve(SystemAccount::SettlementPayable, $wallet->currency);

            return $this->ledger->post(
                PostingDraft::for(TransactionType::WalletWithdrawal)
                    ->describedAs('Wallet withdrawal')
                    ->idempotentBy($idempotencyKey)
                    ->withMetadata($metadata + ['wallet_id' => $wallet->getKey()])
                    ->debit($wallet->ledger_account_id, $amount)
                    ->credit($settlement, $amount)
            );
        });
    }

    /**
     * Move funds from one wallet to another within Reton.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function transfer(Wallet $from, Wallet $to, Money $amount, ?string $idempotencyKey = null, array $metadata = []): Transaction
    {
        $this->assertCurrency($from, $amount);
        $this->assertCurrency($to, $amount);

        if (($replay = $this->ledger->findByIdempotencyKey($idempotencyKey)) !== null) {
            return $replay;
        }

        return DB::transaction(function () use ($from, $to, $amount, $idempotencyKey, $metadata): Transaction {
            $this->assertSufficientFunds($from, $amount);

            return $this->ledger->post(
                PostingDraft::for(TransactionType::InternalTransfer)
                    ->describedAs('Internal transfer')
                    ->idempotentBy($idempotencyKey)
                    ->withMetadata($metadata + [
                        'from_wallet_id' => $from->getKey(),
                        'to_wallet_id' => $to->getKey(),
                    ])
                    ->debit($from->ledger_account_id, $amount)
                    ->credit($to->ledger_account_id, $amount)
            );
        });
    }

    /**
     * Guard that a wallet can spend an amount now (currency + available funds).
     *
     * Intended for callers that compose their own ledger posting (e.g. the
     * Transfers domain) and need the same affordability check as withdraw/transfer.
     */
    public function ensureCanSpend(Wallet $wallet, Money $amount): void
    {
        $this->assertCurrency($wallet, $amount);
        $this->assertSufficientFunds($wallet, $amount);
    }

    /**
     * Protected transfer hold: funds are credited on the ledger but cannot be
     * spent until the protected transfer is released to the receiver.
     */
    public function holdIncoming(Wallet $wallet, Money $amount): void
    {
        $this->assertCurrency($wallet, $amount);

        Wallet::whereKey($wallet->getKey())->increment('held_balance', $amount->amount);
    }

    /**
     * Unlock previously held incoming funds so they become part of available balance.
     */
    public function releaseIncomingHold(Wallet $wallet, Money $amount): void
    {
        $this->assertCurrency($wallet, $amount);

        $updated = Wallet::whereKey($wallet->getKey())
            ->where('held_balance', '>=', $amount->amount)
            ->decrement('held_balance', $amount->amount);

        if ($updated === 0) {
            throw InsufficientFundsException::heldFor((string) $wallet->getKey(), $wallet->fresh()->available(), $amount);
        }
    }

    /**
     * Reverse a protected transfer: unwind the receiver's pending hold and move
     * ledger funds back to the sender (callback refund / dispute upheld).
     */
    public function reverseProtected(
        Wallet $receiver,
        Wallet $sender,
        Money $amount,
        ?string $idempotencyKey = null,
        ?string $description = null,
    ): Transaction {
        $this->assertCurrency($receiver, $amount);
        $this->assertCurrency($sender, $amount);

        if (($replay = $this->ledger->findByIdempotencyKey($idempotencyKey)) !== null) {
            return $replay;
        }

        return DB::transaction(function () use ($receiver, $sender, $amount, $idempotencyKey, $description): Transaction {
            $locked = Wallet::whereKey($receiver->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->balance < $amount->amount || $locked->held_balance < $amount->amount) {
                throw InsufficientFundsException::heldFor((string) $receiver->getKey(), $locked->available(), $amount);
            }

            Wallet::whereKey($receiver->getKey())
                ->where('held_balance', '>=', $amount->amount)
                ->decrement('held_balance', $amount->amount);

            return $this->ledger->post(
                PostingDraft::for(TransactionType::CallbackRefund)
                    ->describedAs($description ?? 'Protected transfer refunded to sender')
                    ->idempotentBy($idempotencyKey)
                    ->withMetadata([
                        'from_wallet_id' => $receiver->getKey(),
                        'to_wallet_id' => $sender->getKey(),
                    ])
                    ->debit($receiver->ledger_account_id, $amount)
                    ->credit($sender->ledger_account_id, $amount)
            );
        });
    }

    private function assertCurrency(Wallet $wallet, Money $amount): void
    {
        if ($wallet->currency !== $amount->currency) {
            throw WalletCurrencyMismatchException::between($wallet->currency, $amount->currency);
        }
    }

    private function assertSufficientFunds(Wallet $wallet, Money $amount): void
    {
        $fresh = Wallet::whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

        if ($amount->amount > $fresh->availableMinor()) {
            throw InsufficientFundsException::for(
                (string) $wallet->getKey(),
                $fresh->available(),
                $amount,
            );
        }
    }
}
