<?php

declare(strict_types=1);

namespace App\Domain\Bills\Services;

use App\Domain\Bills\Enums\BillCategory;
use App\Domain\Bills\Enums\BillStatus;
use App\Domain\Bills\Models\BillPayment;
use App\Domain\Bills\Remita\Contracts\BillProviderGateway;
use App\Domain\Bills\Remita\Data\BillPaymentInstruction;
use App\Domain\Bills\Remita\Data\RrrInquiry;
use App\Domain\Bills\Remita\Exceptions\BillProviderException;
use App\Domain\Ledger\Data\PostingDraft;
use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates outbound bill payments (airtime, utilities, Remita RRR) via a
 * bill provider.
 *
 * Two-phase ledger, identical in shape to a payout: paying a bill reserves the
 * funds (wallet -> settlement payable, through the audited WalletService). When
 * the provider confirms the bill the funds settle out of house cash; if it
 * fails they are returned to the wallet. Money only ever moves through the
 * ledger.
 */
class BillPaymentService
{
    private const PROVIDER = 'remita';

    public function __construct(
        private readonly BillProviderGateway $gateway,
        private readonly WalletService $wallets,
        private readonly LedgerService $ledger,
        private readonly SystemAccountResolver $system,
    ) {}

    /**
     * Resolve a Remita Retrieval Reference to the outstanding bill it represents.
     */
    public function lookupRrr(string $rrr): RrrInquiry
    {
        $inquiry = $this->gateway->lookupRrr($rrr);

        if ($inquiry === null) {
            throw BillProviderException::unknownRrr($rrr);
        }

        return $inquiry;
    }

    public function pay(
        User $user,
        Wallet $wallet,
        BillCategory $category,
        string $billerCode,
        string $billerName,
        string $customerReference,
        Money $amount,
    ): BillPayment {
        return DB::transaction(function () use ($user, $wallet, $category, $billerCode, $billerName, $customerReference, $amount): BillPayment {
            $reference = 'BILL-'.Str::upper((string) Str::ulid());

            // Reserve the funds: wallet -> settlement payable.
            $reservation = $this->wallets->withdraw($wallet, $amount, $reference, [
                'channel' => 'bill_payment',
                'category' => $category->value,
            ]);

            $bill = BillPayment::create([
                'reference' => $reference,
                'user_id' => $user->getKey(),
                'wallet_id' => $wallet->getKey(),
                'provider' => self::PROVIDER,
                'status' => BillStatus::Pending,
                'category' => $category,
                'biller_code' => $billerCode,
                'biller_name' => $billerName,
                'customer_reference' => $customerReference,
                'amount' => $amount->amount,
                'currency' => $amount->currency,
                'reservation_transaction_id' => $reservation->id,
            ]);

            $result = $this->gateway->payBill(new BillPaymentInstruction(
                reference: $reference,
                category: $category,
                billerCode: $billerCode,
                customerReference: $customerReference,
                amount: $amount,
                narration: $billerName,
            ));

            $bill->update(['provider_reference' => $result->providerReference]);

            // Bills usually confirm synchronously; settle or refund immediately.
            // Anything still pending is closed out later by reconcile().
            if ($result->isCompleted()) {
                $this->settle($bill);
            } elseif ($result->isFailed()) {
                $this->reverse($bill, 'Provider declined the bill payment');
            }

            return $bill->refresh();
        });
    }

    public function reconcile(BillPayment $bill): bool
    {
        if (! $bill->isPending() || $bill->provider_reference === null) {
            return false;
        }

        $remote = $this->gateway->fetchBill($bill->provider_reference);

        if ($remote === null) {
            return false;
        }

        if ($remote->isSuccessful()) {
            $this->settle($bill);

            return true;
        }

        if ($remote->status === 'failed') {
            $this->reverse($bill, 'reconciliation: provider reported failure');

            return true;
        }

        return false;
    }

    public function settle(BillPayment $bill): void
    {
        DB::transaction(function () use ($bill): void {
            $amount = Money::of($bill->amount, $bill->currency);

            $transaction = $this->ledger->post(
                PostingDraft::for(TransactionType::BillPayment)
                    ->describedAs('Bill paid — '.$bill->biller_name)
                    ->idempotentBy($bill->reference.':settle')
                    ->debit($this->system->resolve(SystemAccount::SettlementPayable, $bill->currency), $amount)
                    ->credit($this->system->resolve(SystemAccount::Cash, $bill->currency), $amount)
            );

            $bill->update([
                'status' => BillStatus::Completed,
                'settlement_transaction_id' => $transaction->id,
                'processed_at' => now(),
            ]);
        });
    }

    public function reverse(BillPayment $bill, string $reason): void
    {
        DB::transaction(function () use ($bill, $reason): void {
            $amount = Money::of($bill->amount, $bill->currency);
            $walletAccountId = (string) Wallet::findOrFail($bill->wallet_id)->ledger_account_id;

            $transaction = $this->ledger->post(
                PostingDraft::for(TransactionType::Reversal)
                    ->describedAs('Bill payment reversed — funds returned')
                    ->idempotentBy($bill->reference.':reverse')
                    ->debit($this->system->resolve(SystemAccount::SettlementPayable, $bill->currency), $amount)
                    ->credit($walletAccountId, $amount)
            );

            $bill->update([
                'status' => BillStatus::Failed,
                'settlement_transaction_id' => $transaction->id,
                'failure_reason' => $reason,
                'processed_at' => now(),
            ]);
        });
    }
}
