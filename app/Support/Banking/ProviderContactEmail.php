<?php

declare(strict_types=1);

namespace App\Support\Banking;

use App\Models\User;

/**
 * Contact email registered with ALATPay/Wema for deposit accounts.
 *
 * Bank transaction alerts are emailed to whatever address the provider stores.
 * Reton therefore registers a Reton-owned address (not the customer's inbox)
 * so customers only receive Reton-branded alerts from our mailer.
 */
final class ProviderContactEmail
{
    public static function forUser(User $user): string
    {
        $domain = strtolower(trim((string) config('services.alatpay.provider_contact_domain', 'va.retonpay.com')));

        if ($domain === '' || ! str_contains($domain, '.')) {
            $domain = 'va.retonpay.com';
        }

        $local = 'u'.substr(hash('sha256', 'reton-va:'.$user->getKey()), 0, 20);

        return $local.'@'.$domain;
    }

    /**
     * Emails to try when recovering an existing ALATPay Individual wallet
     * (new Reton contact address + legacy customer email on older accounts).
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
