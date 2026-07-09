<?php

declare(strict_types=1);

namespace App\Domain\Bills\Support;

use App\Domain\Bills\Enums\BillCategory;

/**
 * Maps Reton biller slugs to Interswitch Quickteller payment codes.
 *
 * Sandbox codes from Interswitch QA docs; override per biller in production
 * via your Quickteller merchant dashboard.
 *
 * @see https://docs.interswitchgroup.com/docs/bills-payment-1
 */
final class BillPaymentCodeResolver
{
    public static function normalizeCode(string $billerCode): string
    {
        return $billerCode === '9mobile' ? 't2' : $billerCode;
    }

    public static function resolve(string $billerCode, BillCategory $category): ?string
    {
        $billerCode = self::normalizeCode($billerCode);

        $map = config('reton.bills.payment_codes', []);
        $entry = $map[$billerCode] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        return $entry[$category->value] ?? $entry['default'] ?? null;
    }
}
