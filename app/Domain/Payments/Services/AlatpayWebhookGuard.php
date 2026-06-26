<?php

declare(strict_types=1);

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Alatpay\AlatpaySignatureVerifier;
use App\Domain\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Payments\Models\WebhookEvent;
use Illuminate\Support\Str;

/**
 * The single trusted entry point for inbound AlatPay webhooks: verifies the
 * HMAC signature and records the event under a unique key so each is processed
 * at most once. Deposit and payout handlers share it.
 */
class AlatpayWebhookGuard
{
    private const PROVIDER = 'alatpay';

    public function __construct(private readonly AlatpaySignatureVerifier $signatures) {}

    /**
     * Verify and de-duplicate a webhook.
     *
     * @return array{0: WebhookEvent, 1: array<string, mixed>, 2: bool} the event,
     *                                                                  decoded payload, and whether it is fresh (not already handled).
     */
    public function admit(string $rawPayload, ?string $signature): array
    {
        if (! $this->signatures->verify($rawPayload, $signature)) {
            throw InvalidWebhookSignatureException::make();
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($rawPayload, true);
        $eventId = (string) ($payload['id'] ?? Str::uuid());

        $event = WebhookEvent::firstOrCreate(
            ['provider' => self::PROVIDER, 'event_id' => $eventId],
            [
                'type' => (string) ($payload['type'] ?? 'unknown'),
                'signature_valid' => true,
                'status' => 'received',
                'payload' => $payload,
                'created_at' => now(),
            ],
        );

        $fresh = $event->wasRecentlyCreated || $event->status === 'received';

        return [$event, $payload, $fresh];
    }
}
