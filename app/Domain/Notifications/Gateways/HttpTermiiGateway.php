<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Gateways;

use App\Domain\Notifications\Contracts\SmsGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Termii Go / Termii API v3 — SMS and optional WhatsApp OTP delivery.
 *
 * @see https://developers.termii.com/
 */
final class HttpTermiiGateway implements SmsGateway
{
    public function send(string $to, string $message, string $channel = 'sms'): void
    {
        $to = $this->normaliseMsisdn($to);
        $apiKey = (string) config('services.termii.api_key');
        $sender = (string) config('services.termii.sender_id', 'Reton');

        if ($apiKey === '') {
            throw new RuntimeException('Termii API key is not configured.');
        }

        $endpoint = $channel === 'whatsapp'
            ? '/api/whatsapp/send'
            : '/api/sms/send';

        $payload = [
            'api_key' => $apiKey,
            'to' => $to,
            'from' => $sender,
            'sms' => $message,
            'type' => 'plain',
            'channel' => (string) config('services.termii.channel', 'generic'),
        ];

        if ($channel === 'whatsapp') {
            $payload = [
                'api_key' => $apiKey,
                'to' => $to,
                'from' => $sender,
                'type' => 'plain',
                'channel' => 'whatsapp',
                'sms' => $message,
            ];
        }

        $response = $this->client()->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Termii delivery failed (HTTP '.$response->status().').');
        }
    }

    public function ping(): bool
    {
        $apiKey = (string) config('services.termii.api_key');

        if ($apiKey === '') {
            return false;
        }

        try {
            $response = $this->client()->get('/api/balance', ['api_key' => $apiKey]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.termii.base_url', 'https://api.ng.termii.com'), '/'))
            ->timeout((int) config('services.termii.timeout', 15))
            ->acceptJson()
            ->asJson();
    }

    private function normaliseMsisdn(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '234'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '234') && strlen($digits) === 10) {
            $digits = '234'.$digits;
        }

        return $digits;
    }
}
