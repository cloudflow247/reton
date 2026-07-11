<?php

declare(strict_types=1);

namespace App\Support\Banking;

use App\Models\User;

/**
 * Contact email registered with ALATPay/Wema for customer deposit accounts.
 *
 * Wema bank alerts go to whatever address the provider stores. Those alerts are
 * for Reton operations (CEO / merchant inbox) — never the end customer.
 *
 * Prefer a plus-alias of the ALATPay merchant email so every VA stays unique for
 * duplicate-BVN recovery while all bank mail still lands in the CEO inbox.
 */
final class ProviderContactEmail
{
    public static function forUser(User $user): string
    {
        $tag = 'va'.substr(hash('sha256', 'reton-va:'.$user->getKey()), 0, 12);
        $merchant = strtolower(trim((string) config('services.alatpay.merchant_email', '')));

        if ($merchant !== '' && str_contains($merchant, '@')) {
            [$local, $domain] = explode('@', $merchant, 2);
            $local = explode('+', $local, 2)[0];

            if ($local !== '' && $domain !== '' && str_contains($domain, '.')) {
                return $local.'+'.$tag.'@'.$domain;
            }
        }

        $domain = strtolower(trim((string) config('services.alatpay.provider_contact_domain', 'va.retonpay.com')));

        if ($domain === '' || ! str_contains($domain, '.')) {
            $domain = 'va.retonpay.com';
        }

        return 'u'.substr(hash('sha256', 'reton-va:'.$user->getKey()), 0, 20).'@'.$domain;
    }

    /**
     * Emails to try when recovering an existing ALATPay Individual wallet
     * (merchant plus-alias / Reton contact address + legacy customer email).
     *
     * @return list<string>
     */
    public static function recoveryCandidates(User $user): array
    {
        return array_values(array_unique(array_filter([
            strtolower(self::forUser($user)),
            strtolower(trim((string) $user->email)),
        ])));
    }
}
