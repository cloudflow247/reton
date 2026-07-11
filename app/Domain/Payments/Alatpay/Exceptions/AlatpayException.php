<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use RuntimeException;

final class AlatpayException extends RuntimeException implements RenderableApiException
{
    public static function requestFailed(string $operation, int $status, ?string $detail = null): self
    {
        $base = "AlatPay request [{$operation}] failed with HTTP {$status}.";

        return new self($detail !== null && $detail !== '' ? "{$base} {$detail}" : $base);
    }

    public function apiStatus(): int
    {
        $status = $this->httpStatus();

        return match ($status) {
            400, 404, 422 => 422,
            401, 403 => 502,
            408, 503, 504 => 503,
            default => 502,
        };
    }

    public function apiCode(): string
    {
        return 'alatpay_error';
    }

    public function isDuplicateIndividualBvn(): bool
    {
        return str_contains(
            strtolower($this->getMessage()),
            'bvn has been used to create an individual static account',
        );
    }

    /** Short message safe to show in validation errors and flash banners. */
    public function userFacingMessage(string $fallback): string
    {
        if ($this->isDuplicateIndividualBvn()) {
            return 'This BVN already has a deposit account for this business, but we could not match it to your email. Use the same email as before, or contact support.';
        }

        $operation = $this->operation();
        $status = $this->httpStatus();
        $detail = trim((string) preg_replace('/^AlatPay request \[[^\]]+\] failed with HTTP \d+\.?\s*/', '', $this->getMessage()));
        $isFundingLink = in_array($operation, ['createPaymentLink', 'createCollection'], true);
        $isBvnOp = in_array($operation, [
            'provisionStaticAccount',
            'verifyStaticAccount',
            'confirmBvnOtp',
            'resendBvnOtp',
            'pingStaticWallet',
        ], true);

        if ($detail !== '' && ! str_starts_with($detail, 'AlatPay request')) {
            // Prefer our own copy for known funding-product gaps (ALATPay often returns bare 404).
            if (! ($isFundingLink && in_array($status, [404, 501, 503], true))) {
                return mb_strlen($detail) > 180 ? mb_substr($detail, 0, 177).'…' : $detail;
            }
        }

        if ($operation === 'createPaymentLink') {
            return match ($status) {
                400, 404, 422, 501 => 'Card and checkout are not available from ALATPay for this business yet. Use One-time transfer or your permanent deposit account.',
                401, 403 => 'Payment provider session was rejected. Ask an admin to refresh Integrations credentials, then try again.',
                408, 503, 504 => 'Checkout is temporarily unreachable. Please try again, or use One-time transfer.',
                default => $fallback,
            };
        }

        return match ($status) {
            400, 404, 422 => $isBvnOp
                ? 'That BVN was rejected. Double-check the number — it must be your real BVN, not a demo value.'
                : ($isFundingLink
                    ? 'ALATPay could not start that payment. Try One-time transfer or your permanent deposit account.'
                    : $fallback),
            401, 403 => 'Payment provider session was rejected. Ask an admin to refresh Integrations credentials, then try again.',
            408, 503, 504 => 'Verification timed out or is unreachable. Please try again in a moment.',
            default => $fallback,
        };
    }

    public function httpStatus(): ?int
    {
        if (preg_match('/failed with HTTP (\d+)/', $this->getMessage(), $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    public function operation(): ?string
    {
        if (preg_match('/AlatPay request \[([^\]]+)\]/', $this->getMessage(), $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
