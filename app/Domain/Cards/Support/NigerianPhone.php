<?php

declare(strict_types=1);

namespace App\Domain\Cards\Support;

use App\Models\User;

final class NigerianPhone
{
    public static function toInternational(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '234')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '234'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '234'.$digits;
        }

        return $digits;
    }

    /** @return array{0: string, 1: string} */
    public static function splitName(User $user): array
    {
        $parts = preg_split('/\s+/', trim($user->name)) ?: [];
        $first = $parts[0] ?? 'Reton';
        $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'User';

        return [$first, $last];
    }
}
