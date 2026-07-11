<?php

declare(strict_types=1);

namespace App\Domain\Payments\Paystack\Services;

use App\Domain\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Payments\Models\WebhookEvent;
use App\Domain\Payments\Paystack\PaystackSignatureVerifier;

/**
 * Admit Paystack webhooks once: verify signature, de-dupe by event id.
 */
final class PaystackWebhookGuard
{
    private const PROVIDER = 'paystack';

    public function __construct(private readonly PaystackSignatureVerifier $signatures) {}

    /**
     * @return array{0: WebhookEvent, 1: array<string, mixed>, 2: bool}
     */
    public function admit(string $rawPayload, ?string $signature): array
    {
        if (! $this->signatures->verify($rawPayload, $signature)) {
            throw InvalidWebhookSignatureException::make();
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($rawPayload, true);
        $data = (array) ($payload['data'] ?? []);
        $eventType = (string) ($payload['event'] ?? $payload['type'] ?? 'unknown');
        $transferCode = (string) ($data['transfer_code'] ?? '');
        $merchantReference = (string) ($data['reference'] ?? '');
        $dataId = (string) ($data['id'] ?? $payload['id'] ?? '');

        // Paystack payloads often omit a top-level id — key on transfer + event for replay safety.
        $eventId = $dataId !== ''
            ? $dataId
            : ($transferCode !== '' || $merchantReference !== ''
                ? $eventType.':'.($transferCode !== '' ? $transferCode : $merchantReference)
                : hash('sha256', $rawPayload));

        $event = WebhookEvent::firstOrCreate(
            ['provider' => self::PROVIDER, 'event_id' => $eventId],
            [
                'type' => $eventType,
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
