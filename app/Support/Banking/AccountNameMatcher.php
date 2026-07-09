<?php

declare(strict_types=1);

namespace App\Support\Banking;

/**
 * Ensures a bank account name matches the Reton account holder (same-name policy).
 */
final class AccountNameMatcher
{
    public static function matches(string $accountName, string $userName): bool
    {
        $accountTokens = self::tokens($accountName);
        $userTokens = self::tokens($userName);

        if ($accountTokens === [] || $userTokens === []) {
            return false;
        }

        $overlap = count(array_intersect($accountTokens, $userTokens));

        return $overlap >= min(2, count($userTokens));
    }

    /** @return list<string> */
    private static function tokens(string $name): array
    {
        $normalized = preg_replace('/[^a-z\s]/', ' ', strtolower($name)) ?? '';
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];

        return array_values(array_filter($parts, fn (string $p) => strlen($p) >= 2));
    }
}
