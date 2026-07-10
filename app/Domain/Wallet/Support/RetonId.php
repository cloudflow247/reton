<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Support;

/**
 * Reton peer-to-peer wallet identifier (RETON ID).
 *
 * Format: R + 8 body digits + 1 Luhn check digit (10 chars total).
 * Deliberately non-numeric so it never collides with NUBAN / ALATPay Wema VAs.
 */
final class RetonId
{
    public const PATTERN = '/^R\d{9}$/';

    public const LENGTH = 10;

    /**
     * Generate a new RETON ID (caller must enforce uniqueness).
     */
    public static function generate(): string
    {
        $body = str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);

        return 'R'.$body.self::luhnCheckDigit($body);
    }

    public static function isValid(?string $value): bool
    {
        $normalized = self::normalize($value);

        if ($normalized === null || preg_match(self::PATTERN, $normalized) !== 1) {
            return false;
        }

        $body = substr($normalized, 1, 8);
        $check = substr($normalized, 9, 1);

        return $check === self::luhnCheckDigit($body);
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $value) ?? '');

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Luhn check digit for an all-digit payload (ISO/IEC 7812 style).
     */
    public static function luhnCheckDigit(string $digits): string
    {
        $sum = 0;
        $alt = true;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];

            if ($alt) {
                $n *= 2;

                if ($n > 9) {
                    $n -= 9;
                }
            }

            $sum += $n;
            $alt = ! $alt;
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }
}
