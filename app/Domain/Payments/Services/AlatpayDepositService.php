<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Kyc\Services\KycLimitService;
use App\Domain\Kyc\Services\KycService;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\CollectionRequest;
use App\Domain\Payments\Alatpay\Data\PaymentLinkRequest;
use App\Domain\Payments\Enums\DepositMethod;
use App\Domain\Payments\Enums\DepositStatus;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Models\WebhookEvent;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Collection;
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
        private readonly KycLimitService $kycLimits,
        private readonly KycService $kyc,
    ) {}

    public function initiate(User $user, Wallet $wallet, Money $amount, DepositMethod $method = DepositMethod::BankTransfer): Deposit
    {
        $bvn = $this->kyc->assertBvnVerifiedForPayments($user);
        $this->kycLimits->assertCanCredit($user, $wallet, $amount);

        return match ($method) {
            DepositMethod::BankTransfer => $this->initiateBankTransfer($user, $wallet, $amount, $bvn),
            DepositMethod::AlatpayCheckout, DepositMethod::AlatpayCard => $this->initiatePaymentLink($user, $wallet, $amount, $method, $bvn),
        };
    }

    public function initiateBankTransfer(User $user, Wallet $wallet, Money $amount, ?string $bvn = null): Deposit
    {
        $bvn ??= $this->kyc->assertBvnVerifiedForPayments($user);
        $deposit = $this->createPendingDeposit($user, $wallet, $amount, DepositMethod::BankTransfer);

        $collection = $this->gateway->createCollection(new CollectionRequest(
            reference: $deposit->reference,
            amount: $amount,
            customerName: (string) $user->name,
            customerEmail: (string) $user->email,
            customerPhone: $user->phone,
            customerBvn: $bvn,
        ));

        $deposit->update([
            'provider_reference' => $collection->providerReference,
            'virtual_account' => $collection->virtualAccount(),
        ]);

        return $deposit->refresh();
    }

    public function initiatePaymentLink(User $user, Wallet $wallet, Money $amount, DepositMethod $method, ?string $bvn = null): Deposit
    {
        $bvn ??= $this->kyc->assertBvnVerifiedForPayments($user);
        $deposit = $this->createPendingDeposit($user, $wallet, $amount, $method);

        $link = $this->gateway->createPaymentLink(new PaymentLinkRequest(
            reference: $deposit->reference,
            amount: $amount,
            title: 'Fund Reton wallet',
            description: 'Add money to your protected Reton wallet',
            customerEmail: (string) $user->email,
            customerName: (string) $user->name,
            customerPhone: $user->phone,
            customerBvn: $bvn,
            redirectUrl: route('add-money.return', ['reference' => $deposit->reference]),
            channel: $method->alatpayChannel(),
        ));

        $deposit->update([
            'provider_reference' => $link->providerReference,
            'metadata' => [
                'method' => $method->value,
                'payment_link_url' => $link->paymentLinkUrl,
                'expires_at' => $link->expiresAt,
            ],
        ]);

        return $deposit->refresh();
    }

    public function findForUser(User $user, string $reference): ?Deposit
    {
        return Deposit::query()
            ->where('user_id', $user->getKey())
            ->where('reference', $reference)
            ->first();
    }

    /** @return Collection<int, Deposit> */
    public function openDepositsFor(User $user, int $limit = 5): Collection
    {
        return Deposit::query()
            ->where('user_id', $user->getKey())
            ->where('status', DepositStatus::Pending)
            ->latest()
            ->limit($limit)
            ->get();
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
    public function process(WebhookEvent $event, array $data): void
    {
        $reference = (string) ($data['reference'] ?? '');
        $deposit = $this->findDepositByWebhookReference($reference);

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

    private function createPendingDeposit(User $user, Wallet $wallet, Money $amount, DepositMethod $method): Deposit
    {
        return Deposit::create([
            'reference' => 'DEP-'.Str::upper((string) Str::ulid()),
            'user_id' => $user->getKey(),
            'wallet_id' => $wallet->getKey(),
            'provider' => self::PROVIDER,
            'status' => DepositStatus::Pending,
            'amount' => $amount->amount,
            'currency' => $amount->currency,
            'metadata' => ['method' => $method->value],
        ]);
    }

    private function findDepositByWebhookReference(string $reference): ?Deposit
    {
        if ($reference === '') {
            return null;
        }

        return Deposit::query()
            ->where('provider', self::PROVIDER)
            ->where(function ($query) use ($reference): void {
                $query->where('provider_reference', $reference)
                    ->orWhere('reference', $reference);
            })
            ->first();
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
