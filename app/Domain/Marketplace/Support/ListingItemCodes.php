<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Support;

use App\Domain\Marketplace\Models\DigitalListing;

/**
 * Human-readable codes sellers share alongside listing URLs (e.g. RTN-7K3M9P).
 */
final class ListingItemCodes
{
    private const string PREFIX = 'RTN-';

    private const string ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public static function generate(): string
    {
        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $code = self::PREFIX.$suffix;
        } while (DigitalListing::query()->where('item_code', $code)->exists());

        return $code;
    }

    public static function normalize(string $input): ?string
    {
        $compact = strtoupper(preg_replace('/\s+/', '', $input) ?? '');

        if (preg_match('/^RTN-?(['.self::ALPHABET.']{6})$/', $compact, $matches) === 1) {
            return self::PREFIX.$matches[1];
        }

        if (preg_match('/^(['.self::ALPHABET.']{6})$/', $compact, $matches) === 1) {
            return self::PREFIX.$matches[1];
        }

        return null;
    }
}
