<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Fees\Enums\FeeRail;
use App\Domain\Fees\Services\PlatformFeeService;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\PaymentLinkRequest;
use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Models\WebhookEvent;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates "request money" via AlatPay payment links.
 *
 * A requester raises a fixed-amount request; AlatPay mints a hosted link any
 * payer can settle. Inbound money is only ever credited through the audited
 * WalletService ledger path, and only after a signature-verified, de-duplicated
 * webhook (or a reconciliation confirming the payment). Three independent guards
 * prevent double-credit: the webhook-event unique key, the request status, and
 * the ledger idempotency key.
 */
class PaymentRequestService
{
    private const PROVIDER = 'alatpay';

    public function __construct(
        private readonly AlatpayGateway $gateway,
        private readonly WalletService $wallets,
        private readonly AlatpayWebhookGuard $guard,
        private readonly PlatformFeeService $fees,
    ) {}

    public function create(User $user, Wallet $wallet, Money $amount, string $title, ?string $description = null): PaymentRequest
    {
        $request = PaymentRequest::create([
            'reference' => 'REQ-'.Str::upper((string) Str::ulid()),
            'requester_user_id' => $user->getKey(),
            'wallet_id' => $wallet->getKey(),
            'provider' => self::PROVIDER,
            'status' => PaymentRequestStatus::Pending,
            'amount' => $amount->amount,
            'currency' => $amount->currency,
            'title' => $title,
            'description' => $description,
        ]);

        $link = $this->gateway->createPaymentLink(new PaymentLinkRequest(
            reference: $request->reference,
            amount: $amount,
            title: $title,
            description: (string) $description,
            customerEmail: (string) $user->email,
        ));

        $request->update([
            'provider_reference' => $link->providerReference,
            'payment_link_url' => $link->paymentLinkUrl,
            'expires_at' => $link->expiresAt,
        ]);

        return $request->refresh();
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

    public function reconcile(PaymentRequest $request): bool
    {
        if (! $request->isOpen() || $request->provider_reference === null) {
            return false;
        }

        $remote = $this->gateway->fetchTransaction($request->provider_reference);

        if ($remote === null
            || ! $remote->isSuccessful()
            || $remote->amount !== $request->amount
            || $remote->currency !== $request->currency
        ) {
            return false;
        }

        $this->credit($request, []);

        return true;
    }

    public function cancel(PaymentRequest $request): PaymentRequest
    {
        if ($request->isOpen()) {
            $request->update(['status' => PaymentRequestStatus::Cancelled]);
        }

        return $request->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function process(WebhookEvent $event, array $data): void
    {
        $reference = (string) ($data['reference'] ?? '');
        $request = PaymentRequest::where('provider', self::PROVIDER)
            ->where('provider_reference', $reference)
            ->first();

        if (! $request instanceof PaymentRequest) {
            $event->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }

        if ($request->isPaid()) {
            $event->update(['status' => 'processed', 'processed_at' => now()]);

            return;
        }

        if (! $request->isOpen()) {
            // Cancelled or expired: no longer collectible.
            $event->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }

        $succeeded = ($data['status'] ?? null) === 'completed'
            && (int) ($data['amount'] ?? 0) === $request->amount
            && (string) ($data['currency'] ?? '') === $request->currency;

        if (! $succeeded) {
            $event->update(['status' => 'failed', 'processed_at' => now()]);

            return;
        }

        $this->credit($request, (array) ($data['customer'] ?? []));
        $event->update(['status' => 'processed', 'processed_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function credit(PaymentRequest $request, array $customer): void
    {
        DB::transaction(function () use ($request, $customer): void {
            $wallet = Wallet::findOrFail($request->wallet_id);

            $credited = Money::of($request->amount, $request->currency);

            $transaction = $this->wallets->fund(
                $wallet,
                $credited,
                $request->reference, // ledger idempotency key
                ['payment_request_id' => $request->id, 'provider' => self::PROVIDER],
            );

            $this->fees->chargeWallet(
                $wallet->fresh(),
                FeeRail::Deposit,
                $credited,
                'fee:deposit:'.$request->reference,
            );

            $request->update([
                'status' => PaymentRequestStatus::Paid,
                'transaction_id' => $transaction->id,
                'payer_name' => isset($customer['name']) ? (string) $customer['name'] : $request->payer_name,
                'payer_email' => isset($customer['email']) ? (string) $customer['email'] : $request->payer_email,
                'paid_at' => now(),
            ]);
        });
    }
}
