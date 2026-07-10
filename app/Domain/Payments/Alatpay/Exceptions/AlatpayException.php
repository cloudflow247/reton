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

    /** Short message safe to show in validation errors. */
    public function userFacingMessage(string $fallback): string
    {
        $detail = trim((string) preg_replace('/^AlatPay request \[[^\]]+\] failed with HTTP \d+\.?\s*/', '', $this->getMessage()));

        if ($detail === '' || str_starts_with($detail, 'AlatPay request')) {
            return $fallback;
        }

        // Cap length — provider messages can be verbose.
        return mb_strlen($detail) > 180 ? mb_substr($detail, 0, 177).'…' : $detail;
    }
}
