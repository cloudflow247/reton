<?php

declare(strict_types=1);

namespace App\Domain\Logistics\Giglogistics\Services;

use App\Domain\Payments\Exceptions\InvalidWebhookSignatureException;
use Illuminate\Support\Facades\Log;

/**
 * Verifies HMAC signatures on Giglogistics partner webhooks.
 */
class GiglogisticsWebhookGuard
{
    public function verify(string $rawBody, ?string $signature): void
    {
        $secret = (string) config('services.giglogistics.webhook_secret', '');

        if ($secret === '' || $secret === 'disabled') {
            if (app()->environment('production')) {
                Log::warning('Giglogistics webhook secret not configured in production.');
            }

            return;
        }

        if ($signature === null || $signature === '') {
            throw InvalidWebhookSignatureException::make();
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (! hash_equals($expected, $signature)) {
            throw InvalidWebhookSignatureException::make();
        }
    }
}
