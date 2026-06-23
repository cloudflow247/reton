<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\CollectionRequest;
use App\Domain\Payments\Enums\DepositStatus;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Models\WebhookEvent;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates wallet funding via AlatPay collections.
 *
 * Inbound money is only ever credited through the audited WalletService ledger
 * path, and only after a signature-verified, de-duplicated webhook (or a
 * reconciliation confirming the payment). Three independent guards prevent
 * double-credit: the webhook-event unique key, the deposit status, and the
 * ledger idempotency key.
 */
class AlatpayDepositService
{
    private const PROVIDER = 'alatpay';

    public function __construct(
        private readonly AlatpayGateway $gateway,
        private readonly WalletService $wallets,
        private readonly AlatpayWebhookGuard $guard,
    ) {}

    public function initiate(User $user, Wallet $wallet, Money $amount): Deposit
    {
        $deposit = Deposit::create([
            'reference' => 'DEP-'.Str::upper((string) Str::ulid()),
            'user_id' => $user->getKey(),
            'wallet_id' => $wallet->getKey(),
            'provider' => self::PROVIDER,
            'status' => DepositStatus::Pending,
            'amount' => $amount->amount,
            'currency' => $amount->currency,
        ]);

        $collection = $this->gateway->createCollection(new CollectionRequest(
            reference: $deposit->reference,
            amount: $amount,
            customerName: (string) $user->name,
            customerEmail: (string) $user->email,
            customerPhone: $user->phone,
        ));

        $deposit->update([
            'provider_reference' => $collection->providerReference,
            'virtual_account' => $collection->virtualAccount(),
        ]);

        return $deposit->refresh();
    }

    public function handleWebhook(string $rawPayload, ?string $signature): WebhookEvent
    {
        [$event, $payload, $fresh] = $this->guard->admit($rawPayload, $signature);

        // Replay of an already-handled event: nothing to do.
        if (! $fresh) {
            return $event;
        }

        $this->process($event, (array) ($payload['data'] ?? []));

        return $event->refresh();
    }

    public function reconcile(Deposit $deposit): bool
    {
        if ($deposit->status !== DepositStatus::Pending || $deposit->provider_reference === null) {
            return false;
        }

        $remote = $this->gateway->fetchTransaction($deposit->provider_reference);

        if ($remote === null || ! $remote->isSuccessful() || $remote->amount !== $deposit->amount) {
            return false;
        }

        $this->creditDeposit($deposit);

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function process(WebhookEvent $event, array $data): void
    {
        $reference = (string) ($data['reference'] ?? '');
        $deposit = Deposit::where('provider', self::PROVIDER)
            ->where('provider_reference', $reference)
            ->first();

        if (! $deposit instanceof Deposit) {
            $event->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }

        if ($deposit->isCompleted()) {
            $event->update(['status' => 'processed', 'processed_at' => now()]);

            return;
        }

        $succeeded = ($data['status'] ?? null) === 'completed'
            && (int) ($data['amount'] ?? 0) === $deposit->amount
            && (string) ($data['currency'] ?? '') === $deposit->currency;

        if (! $succeeded) {
            $event->update(['status' => 'failed', 'processed_at' => now()]);

            return;
        }

        $this->creditDeposit($deposit);
        $event->update(['status' => 'processed', 'processed_at' => now()]);
    }

    private function creditDeposit(Deposit $deposit): void
    {
        DB::transaction(function () use ($deposit): void {
            $wallet = Wallet::findOrFail($deposit->wallet_id);

            $transaction = $this->wallets->fund(
                $wallet,
                Money::of($deposit->amount, $deposit->currency),
                $deposit->reference, // ledger idempotency key
                ['deposit_id' => $deposit->id, 'provider' => self::PROVIDER],
            );

            $deposit->update([
                'status' => DepositStatus::Completed,
                'transaction_id' => $transaction->id,
                'paid_at' => now(),
            ]);
        });
    }
}
