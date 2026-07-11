<?php

declare(strict_types=1);

namespace App\Support\Banking;

/**
 * Provider VAs often return merchant-prefixed names like
 * "CLOUDFLOW TECHNOLOGY LTD - GABRIEL MOGAJI". Reton shows the personal name only.
 */
final class FundingAccountName
{
    public static function display(?string $providerAccountName, string $profileName): string
    {
        $profile = trim($profileName);

        if ($profile === '') {
            return trim((string) $providerAccountName);
        }

        $preferred = mb_strtoupper($profile);

        if ($providerAccountName === null || trim($providerAccountName) === '') {
            return $preferred;
        }

        $provider = trim($providerAccountName);

        if (AccountNameMatcher::matches($provider, $profile)) {
            return $preferred;
        }

        foreach ([' - ', ' – ', ' — ', ' / ', ' | '] as $separator) {
            if (! str_contains($provider, $separator)) {
                continue;
            }

            $parts = array_values(array_filter(array_map(
                static fn (string $part): string => trim($part),
                explode($separator, $provider),
            )));

            $tail = end($parts);

            if (is_string($tail) && $tail !== '' && AccountNameMatcher::matches($tail, $profile)) {
                return $preferred;
            }
        }

        return $provider;
    }
}
