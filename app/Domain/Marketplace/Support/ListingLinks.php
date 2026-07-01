<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Support;

use App\Domain\Marketplace\Models\DigitalListing;

/**
 * Stable listing URLs for web share sheets and future mobile deep links.
 *
 * Web paths use /l/{uuid} so iOS Universal Links and Android App Links can
 * claim a single prefix (see WellKnownController).
 */
final class ListingLinks
{
    public static function path(DigitalListing $listing): string
    {
        $prefix = rtrim((string) config('reton.links.listing_path', '/l'), '/');

        return $prefix.'/'.$listing->id;
    }

    public static function web(DigitalListing $listing): string
    {
        return rtrim((string) config('reton.links.public_base'), '/').self::path($listing);
    }

    /** Custom scheme fallback until universal links are verified on device. */
    public static function app(DigitalListing $listing): string
    {
        $scheme = (string) config('reton.links.app_scheme', 'reton');

        return $scheme.'://l/'.$listing->id;
    }
}
