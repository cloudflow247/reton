<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Models\WebhookEvent;
use Illuminate\Support\Str;

/**
 * The single entry point for inbound AlatPay webhooks. Admits and de-duplicates
 * each event exactly once via the guard, then dispatches by intent:
 *  - transfer.* events settle payouts;
 *  - collection events that match a payment request credit that request;
 *  - all other collections credit deposits.
 *
 * Routing by matching the provider reference (not just the event type) is what
 * lets payment-link payments and wallet-funding deposits share one webhook URL.
 */
class AlatpayWebhookRouter
{
    private const PROVIDER = 'alatpay';

    public function __construct(
        private readonly AlatpayWebhookGuard $guard,
        private readonly AlatpayDepositService $deposits,
        private readonly PayoutService $payouts,
        private readonly PaymentRequestService $paymentRequests,
    ) {}

    public function handle(string $rawPayload, ?string $signature): WebhookEvent
    {
        [$event, $payload, $fresh] = $this->guard->admit($rawPayload, $signature);

        if (! $fresh) {
            return $event;
        }

        /** @var array<string, mixed> $data */
        $data = (array) ($payload['data'] ?? []);
        $type = (string) ($payload['type'] ?? '');

        if (Str::startsWith($type, 'transfer')) {
            $this->payouts->process($event, $data, 'alatpay');

            return $event->refresh();
        }

        $reference = (string) ($data['reference'] ?? '');
        $isPaymentRequest = $reference !== '' && PaymentRequest::query()
            ->where('provider', self::PROVIDER)
            ->where('provider_reference', $reference)
            ->exists();

        if ($isPaymentRequest) {
            $this->paymentRequests->process($event, $data);
        } else {
            $this->deposits->process($event, $data);
        }

        return $event->refresh();
    }
}
