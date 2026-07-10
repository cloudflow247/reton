<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Exceptions;

use RuntimeException;

final class AlatpayException extends RuntimeException
{
    public static function requestFailed(string $operation, int $status, ?string $detail = null): self
    {
        $base = "AlatPay request [{$operation}] failed with HTTP {$status}.";

        return new self($detail !== null && $detail !== '' ? "{$base} {$detail}" : $base);
    }

    public function isDuplicateIndividualBvn(): bool
    {
        return str_contains(
            strtolower($this->getMessage()),
            'bvn has been used to create an individual static account',
        );
    }

    /** Short message safe to show in validation errors. */
    public function userFacingMessage(string $fallback): string
    {
        if ($this->isDuplicateIndividualBvn()) {
            return 'This BVN already has an ALATPay deposit account for this business, but we could not match it to your email. Use the same email as on ALATPay or contact support.';
        }

        $status = null;
        if (preg_match('/failed with HTTP (\d+)/', $this->getMessage(), $matches) === 1) {
            $status = (int) $matches[1];
        }

        $detail = trim((string) preg_replace('/^AlatPay request \[[^\]]+\] failed with HTTP \d+\.?\s*/', '', $this->getMessage()));

        if ($detail !== '' && ! str_starts_with($detail, 'AlatPay request')) {
            return mb_strlen($detail) > 180 ? mb_substr($detail, 0, 177).'…' : $detail;
        }

        return match ($status) {
            400, 404, 422 => 'ALATPay rejected that BVN. Double-check the number — it must be your real BVN, not a demo value.',
            401, 403 => 'ALATPay session was rejected. Ask an admin to set merchant email/password and Business ID in Integrations, then Test connection.',
            408, 503, 504 => 'ALATPay timed out or is unreachable. Please try again in a moment.',
            default => $fallback,
        };
    }
}
