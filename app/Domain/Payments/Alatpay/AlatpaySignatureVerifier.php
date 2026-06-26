<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay;

/**
 * Validates (and, for tests, produces) the HMAC signature AlatPay attaches to
 * its webhooks. A constant-time comparison guards against timing attacks.
 */
class AlatpaySignatureVerifier
{
    public function sign(string $payload): string
    {
        return hash_hmac('sha512', $payload, $this->secret());
    }

    public function verify(string $payload, ?string $signature): bool
    {
        if ($signature === null || $signature === '' || $this->secret() === '') {
            return false;
        }

        return hash_equals($this->sign($payload), $signature);
    }

    private function secret(): string
    {
        return (string) config('services.alatpay.webhook_secret', '');
    }
}
