<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Ledger\Data\PostingDraft;
use App\Domain\Ledger\Enums\SystemAccount;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Payments\Alatpay\Data\TransferRequest;
use App\Domain\Payments\Contracts\PayoutGateway;
use App\Domain\Payments\Enums\PayoutStatus;
use App\Domain\Payments\Exceptions\PayoutUnavailableException;
use App\Domain\Payments\Models\Payout;
use App\Domain\Payments\Models\WebhookEvent;
use App\Domain\Payments\Paystack\Services\PaystackWebhookGuard;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates outbound payouts to external bank accounts.
 *
 * Two-phase ledger: requesting a payout reserves the funds (wallet ->
 * settlement payable). When the payout provider confirms the transfer the
 * funds settle out of house cash; if it fails they are returned to the wallet.
 */
class PayoutService
{
    public function __construct(
        private readonly PayoutGateway $gateway,
        private readonly WalletService $wallets,
        private readonly LedgerService $ledger,
        private readonly SystemAccountResolver $system,
        private readonly AlatpayWebhookGuard $alatpayGuard,
        private readonly PaystackWebhookGuard $paystackGuard,
    ) {}

    public function provider(): string
    {
        return (string) config('reton.payouts.provider', 'paystack');
    }

    public function request(
        User $user,
        Wallet $wallet,
        Money $amount,
        string $bankCode,
        string $accountNumber,
        string $accountName,
    ): Payout {
        if (! $this->gateway->supportsOutboundTransfers()) {
            throw PayoutUnavailableException::make();
        }

        return DB::transaction(function () use ($user, $wallet, $amount, $bankCode, $accountNumber, $accountName): Payout {
            $reference = 'PO-'.Str::upper((string) Str::ulid());
            $provider = $this->provider();

            $reservation = $this->wallets->withdraw($wallet, $amount, $reference, [
                'channel' => 'payout',
                'provider' => $provider,
            ]);

            $payout = Payout::create([
                'reference' => $reference,
                'user_id' => $user->getKey(),
                'wallet_id' => $wallet->getKey(),
                'provider' => $provider,
                'status' => PayoutStatus::Pending,
                'amount' => $amount->amount,
                'currency' => $amount->currency,
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
                'account_name' => $accountName,
                'reservation_transaction_id' => $reservation->id,
            ]);

            try {
                $transfer = $this->gateway->initiateTransfer(new TransferRequest(
                    reference: $reference,
                    amount: $amount,
                    bankCode: $bankCode,
                    accountNumber: $accountNumber,
                    accountName: $accountName,
                ));
            } catch (\Throwable $e) {
                // Rolling back the transaction undoes the wallet reservation.
                throw $e;
            }

            $payout->update(['provider_reference' => $transfer->providerReference]);

            if ($transfer->status === 'completed') {
                $this->settle($payout->refresh());
            } elseif ($transfer->status === 'failed') {
                $this->reverse($payout->refresh(), 'Provider reported transfer failed immediately');
            }

            return $payout->refresh();
        });
    }

    public function handleAlatpayWebhook(string $rawPayload, ?string $signature): WebhookEvent
    {
        [$event, $payload, $fresh] = $this->alatpayGuard->admit($rawPayload, $signature);

        if (! $fresh) {
            return $event;
        }

        $this->process($event, (array) ($payload['data'] ?? []), 'alatpay');

        return $event->refresh();
    }

    /** @deprecated Use handleAlatpayWebhook — kept for existing call sites. */
    public function handleWebhook(string $rawPayload, ?string $signature): WebhookEvent
    {
        return $this->handleAlatpayWebhook($rawPayload, $signature);
    }

    public function handlePaystackWebhook(string $rawPayload, ?string $signature): WebhookEvent
    {
        [$event, $payload, $fresh] = $this->paystackGuard->admit($rawPayload, $signature);

        if (! $fresh) {
            return $event;
        }

        $eventName = (string) ($payload['event'] ?? '');
        $data = (array) ($payload['data'] ?? []);

        $merchantReference = (string) ($data['reference'] ?? '');
        $transferCode = (string) ($data['transfer_code'] ?? '');

        $status = match (true) {
            str_contains($eventName, 'success') => 'completed',
            str_contains($eventName, 'failed'), str_contains($eventName, 'reversed') => 'failed',
            default => $this->normaliseProviderStatus((string) ($data['status'] ?? '')),
        };

        $data['status'] = $status;
        $data['merchant_reference'] = $merchantReference;
        $data['reference'] = $transferCode !== '' ? $transferCode : $merchantReference;

        $this->process($event, $data, 'paystack');

        return $event->refresh();
    }

    public function reconcile(Payout $payout): bool
    {
        if (! $payout->isPending() || $payout->provider_reference === null) {
            return false;
        }

        if ((string) $payout->provider !== $this->provider()) {
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
        if (! $payout->isPending()) {
            return;
        }

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
        if ($payout->status === PayoutStatus::Failed || $payout->status === PayoutStatus::Completed) {
            return;
        }

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
    public function process(WebhookEvent $event, array $data, ?string $provider = null): void
    {
        $provider ??= $this->provider();
        $providerReference = (string) ($data['reference'] ?? '');
        $merchantReference = (string) ($data['merchant_reference'] ?? '');

        $payout = Payout::query()
            ->where('provider', $provider)
            ->where(function ($query) use ($providerReference, $merchantReference): void {
                if ($providerReference !== '') {
                    $query->where('provider_reference', $providerReference)
                        ->orWhere('reference', $providerReference);
                }
                if ($merchantReference !== '' && $merchantReference !== $providerReference) {
                    $query->orWhere('reference', $merchantReference)
                        ->orWhere('provider_reference', $merchantReference);
                }
            })
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
            'failed' => $this->reverse($payout, ucfirst($provider).' reported the transfer failed'),
            default => null,
        };

        $event->update(['status' => 'processed', 'processed_at' => now()]);
    }

    private function normaliseProviderStatus(string $status): string
    {
        return match (strtolower($status)) {
            'success', 'successful', 'completed' => 'completed',
            'failed', 'reversed', 'abandoned', 'blocked', 'rejected' => 'failed',
            default => 'pending',
        };
    }
}
