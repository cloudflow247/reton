<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Policies;

use App\Domain\Marketplace\Models\DigitalListing;
use App\Models\User;

class DigitalListingPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(?User $user, DigitalListing $listing): bool
    {
        if ($listing->isActive()) {
            return true;
        }

        return $user !== null && (string) $user->getKey() === (string) $listing->seller_id;
    }

    public function purchase(User $user, DigitalListing $listing): bool
    {
        return (string) $user->getKey() !== (string) $listing->seller_id
            && $listing->isActive();
    }
}
