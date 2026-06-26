<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Domain\Ledger\Data\PostingDraft;
use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Exceptions\CurrencyMismatchException;
use App\Domain\Ledger\Exceptions\LedgerAccountNotFoundException;
use App\Domain\Ledger\Exceptions\UnbalancedTransactionException;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single authority over the double-entry ledger.
 *
 * Nothing in Reton may mutate a balance except by posting a balanced
 * transaction through this service. Every posting is atomic, balanced,
 * single-currency and idempotent.
 */
class LedgerService
{
    public function post(PostingDraft $draft): Transaction
    {
        if (($existing = $this->findByIdempotencyKey($draft->idempotencyKey())) !== null) {
            return $existing;
        }

        $this->assertWellFormed($draft);

        return DB::transaction(function () use ($draft): Transaction {
            // Re-check inside the transaction to close the idempotency race.
            if (($existing = $this->findByIdempotencyKey($draft->idempotencyKey())) !== null) {
                return $existing;
            }

            $accounts = $this->lockAccounts($draft);
            $currency = $this->assertCurrencyAlignment($draft, $accounts);
            $amount = $this->totalDebits($draft);

            $transaction = $this->createTransaction($draft, $currency, $amount);
            $this->writeEntries($draft, $transaction, $accounts);
            $this->projectBalances($accounts);

            return $transaction->load('entries');
        });
    }

    /**
     * Return the transaction previously posted under this idempotency key, if any.
     */
    public function findByIdempotencyKey(?string $key): ?Transaction
    {
        if ($key === null) {
            return null;
        }

        return Transaction::with('entries')->where('idempotency_key', $key)->first();
    }

    private function assertWellFormed(PostingDraft $draft): void
    {
        $entries = $draft->entries();

        if (count($entries) < 2) {
            throw UnbalancedTransactionException::tooFewEntries(count($entries));
        }

        $currency = $entries[0]->amount->currency;
        $debits = 0;
        $credits = 0;

        foreach ($entries as $entry) {
            if (! $entry->amount->isPositive()) {
                throw UnbalancedTransactionException::nonPositiveAmount();
            }

            if ($entry->amount->currency !== $currency) {
                throw CurrencyMismatchException::withinPosting($currency, $entry->amount->currency);
            }

            if ($entry->direction === EntryDirection::Debit) {
                $debits += $entry->amount->amount;
            } else {
                $credits += $entry->amount->amount;
            }
        }

        if ($debits !== $credits) {
            throw UnbalancedTransactionException::dueToSides($debits, $credits);
        }
    }

    /**
     * @return Collection<string, LedgerAccount>
     */
    private function lockAccounts(PostingDraft $draft): Collection
    {
        // Lock in a deterministic order to avoid deadlocks under concurrency.
        $ids = collect($draft->entries())
            ->map(fn ($entry) => $entry->accountId)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $accounts = LedgerAccount::whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            if (! $accounts->has($id)) {
                throw LedgerAccountNotFoundException::withId((string) $id);
            }
        }

        return $accounts;
    }

    /**
     * @param  Collection<string, LedgerAccount>  $accounts
     */
    private function assertCurrencyAlignment(PostingDraft $draft, Collection $accounts): string
    {
        $currency = $draft->entries()[0]->amount->currency;

        foreach ($draft->entries() as $entry) {
            $account = $this->accountFor($accounts, $entry->accountId);

            if ($account->currency !== $entry->amount->currency) {
                throw CurrencyMismatchException::accountMismatch($account->currency, $entry->amount->currency);
            }
        }

        return $currency;
    }

    /**
     * @param  Collection<string, LedgerAccount>  $accounts
     */
    private function accountFor(Collection $accounts, string $id): LedgerAccount
    {
        $account = $accounts->get($id);

        if (! $account instanceof LedgerAccount) {
            throw LedgerAccountNotFoundException::withId($id);
        }

        return $account;
    }

    private function totalDebits(PostingDraft $draft): int
    {
        return collect($draft->entries())
            ->filter(fn ($entry) => $entry->direction === EntryDirection::Debit)
            ->sum(fn ($entry) => $entry->amount->amount);
    }

    private function createTransaction(PostingDraft $draft, string $currency, int $amount): Transaction
    {
        $transaction = new Transaction([
            'reference' => $this->generateReference(),
            'idempotency_key' => $draft->idempotencyKey(),
            'type' => $draft->type,
            'status' => TransactionStatus::Posted,
            'currency' => $currency,
            'amount' => $amount,
            'description' => $draft->description(),
            'metadata' => $draft->metadata() ?: null,
            'posted_at' => now(),
        ]);

        if (($initiator = $draft->initiator()) !== null) {
            $transaction->initiator()->associate($initiator);
        }

        if ($draft->reference() !== null) {
            $transaction->reference = $draft->reference();
        }

        $transaction->save();

        return $transaction;
    }

    /**
     * @param  Collection<string, LedgerAccount>  $accounts
     */
    private function writeEntries(PostingDraft $draft, Transaction $transaction, Collection $accounts): void
    {
        foreach ($draft->entries() as $entry) {
            $account = $this->accountFor($accounts, $entry->accountId);

            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'ledger_account_id' => $entry->accountId,
                'direction' => $entry->direction,
                'amount' => $entry->amount->amount,
                'currency' => $entry->amount->currency,
                'created_at' => now(),
            ]);

            if ($entry->direction === EntryDirection::Debit) {
                $account->posted_debits += $entry->amount->amount;
            } else {
                $account->posted_credits += $entry->amount->amount;
            }
        }
    }

    /**
     * Refresh the cached balances on accounts and any wallets backed by them.
     *
     * @param  Collection<string, LedgerAccount>  $accounts
     */
    private function projectBalances(Collection $accounts): void
    {
        foreach ($accounts as $account) {
            $account->save();
        }

        Wallet::whereIn('ledger_account_id', $accounts->keys()->all())
            ->lockForUpdate()
            ->get()
            ->each(function (Wallet $wallet) use ($accounts): void {
                $wallet->balance = $this->accountFor($accounts, (string) $wallet->ledger_account_id)->balanceMinor();
                $wallet->save();
            });
    }

    private function generateReference(): string
    {
        return 'RTN-'.Str::upper((string) Str::ulid());
    }
}
