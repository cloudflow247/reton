<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Ledger\Data\PostingDraft;
use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\TransferRequest;
use App\Domain\Payments\Enums\PayoutStatus;
use App\Domain\Payments\Models\Payout;
use App\Domain\Payments\Models\WebhookEvent;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates outbound payouts to external bank accounts via AlatPay.
 *
 * Two-phase ledger: requesting a payout reserves the funds (wallet ->
 * settlement payable, through the audited WalletService). When AlatPay confirms
 * the transfer the funds settle out of house cash; if it fails they are
 * returned to the wallet. Money only ever moves through the ledger.
 */
class PayoutService
{
    private const PROVIDER = 'alatpay';

    public function __construct(
        private readonly AlatpayGateway $gateway,
        private readonly WalletService $wallets,
        private readonly LedgerService $ledger,
        private readonly SystemAccountResolver $system,
        private readonly AlatpayWebhookGuard $guard,
    ) {}

    public function request(
        User $user,
        Wallet $wallet,
        Money $amount,
        string $bankCode,
        string $accountNumber,
        string $accountName,
    ): Payout {
        return DB::transaction(function () use ($user, $wallet, $amount, $bankCode, $accountNumber, $accountName): Payout {
            $reference = 'PO-'.Str::upper((string) Str::ulid());

            // Reserve the funds: wallet -> settlement payable.
            $reservation = $this->wallets->withdraw($wallet, $amount, $reference, [
                'channel' => 'payout',
            ]);

            $payout = Payout::create([
                'reference' => $reference,
                'user_id' => $user->getKey(),
                'wallet_id' => $wallet->getKey(),
                'provider' => self::PROVIDER,
                'status' => PayoutStatus::Pending,
                'amount' => $amount->amount,
                'currency' => $amount->currency,
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
                'account_name' => $accountName,
                'reservation_transaction_id' => $reservation->id,
            ]);

            $transfer = $this->gateway->initiateTransfer(new TransferRequest(
                reference: $reference,
                amount: $amount,
                bankCode: $bankCode,
                accountNumber: $accountNumber,
                accountName: $accountName,
            ));

            $payout->update(['provider_reference' => $transfer->providerReference]);

            return $payout->refresh();
        });
    }

    public function handleWebhook(string $rawPayload, ?string $signature): WebhookEvent
    {
        [$event, $payload, $fresh] = $this->guard->admit($rawPayload, $signature);

        if (! $fresh) {
            return $event;
        }

        $this->process($event, (array) ($payload['data'] ?? []));

        return $event->refresh();
    }

    public function reconcile(Payout $payout): bool
    {
        if (! $payout->isPending() || $payout->provider_reference === null) {
            return false;
        }

        $remote = $this->gateway->fetchTransfer($payout->provider_reference);

        if ($remote === null) {
            return false;
        }

        if ($remote->isSuccessful()) {
            $this->settle($payout);

            return true;
        }

        if ($remote->status === 'failed') {
            $this->reverse($payout, 'reconciliation: provider reported failure');

            return true;
        }

        return false;
    }

    public function settle(Payout $payout): void
    {
        DB::transaction(function () use ($payout): void {
            $amount = Money::of($payout->amount, $payout->currency);

            $transaction = $this->ledger->post(
                PostingDraft::for(TransactionType::Settlement)
                    ->describedAs('Payout settled to bank')
                    ->idempotentBy($payout->reference.':settle')
                    ->debit($this->system->resolve(SystemAccount::SettlementPayable, $payout->currency), $amount)
                    ->credit($this->system->resolve(SystemAccount::Cash, $payout->currency), $amount)
            );

            $payout->update([
                'status' => PayoutStatus::Completed,
                'settlement_transaction_id' => $transaction->id,
                'processed_at' => now(),
            ]);
        });
    }

    public function reverse(Payout $payout, string $reason): void
    {
        DB::transaction(function () use ($payout, $reason): void {
            $amount = Money::of($payout->amount, $payout->currency);
            $walletAccountId = (string) Wallet::findOrFail($payout->wallet_id)->ledger_account_id;

            $transaction = $this->ledger->post(
                PostingDraft::for(TransactionType::Reversal)
                    ->describedAs('Payout reversed — funds returned')
                    ->idempotentBy($payout->reference.':reverse')
                    ->debit($this->system->resolve(SystemAccount::SettlementPayable, $payout->currency), $amount)
                    ->credit($walletAccountId, $amount)
            );

            $payout->update([
                'status' => PayoutStatus::Failed,
                'settlement_transaction_id' => $transaction->id,
                'failure_reason' => $reason,
                'processed_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function process(WebhookEvent $event, array $data): void
    {
        $reference = (string) ($data['reference'] ?? '');
        $payout = Payout::where('provider', self::PROVIDER)
            ->where('provider_reference', $reference)
            ->first();

        if (! $payout instanceof Payout) {
            $event->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }

        if (! $payout->isPending()) {
            $event->update(['status' => 'processed', 'processed_at' => now()]);

            return;
        }

        match ((string) ($data['status'] ?? '')) {
            'completed' => $this->settle($payout),
            'failed' => $this->reverse($payout, 'AlatPay reported the transfer failed'),
            default => null,
        };

        $event->update(['status' => 'processed', 'processed_at' => now()]);
    }
}
