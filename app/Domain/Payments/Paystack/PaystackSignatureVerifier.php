<?php

declare(strict_types=1);

namespace App\Domain\Payments\Paystack;

/**
 * Verifies Paystack webhook signatures (HMAC SHA512 of the raw body).
 *
 * @see https://paystack.com/docs/payments/webhooks/#verify-events-signature
 */
final class PaystackSignatureVerifier
{
    public function verify(string $rawPayload, ?string $signature): bool
    {
        $secret = (string) (config('services.paystack.webhook_secret') ?: config('services.paystack.secret_key'));

        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $rawPayload, $secret);

        return hash_equals($expected, $signature);
    }

    public function sign(string $rawPayload): string
    {
        $secret = (string) (config('services.paystack.webhook_secret') ?: config('services.paystack.secret_key'));

        return hash_hmac('sha512', $rawPayload, $secret);
    }
}
