<?php

declare(strict_types=1);

use App\Domain\Marketplace\Support\ListingItemCodes;

it('normalizes common item code formats', function () {
    expect(ListingItemCodes::normalize(' rtn-7k3m9p '))->toBe('RTN-7K3M9P')
        ->and(ListingItemCodes::normalize('RTN7K3M9P'))->toBe('RTN-7K3M9P')
        ->and(ListingItemCodes::normalize('7K3M9P'))->toBe('RTN-7K3M9P')
        ->and(ListingItemCodes::normalize('RTN-ABC123'))->toBeNull();
});
