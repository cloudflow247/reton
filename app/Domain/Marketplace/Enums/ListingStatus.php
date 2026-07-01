<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Enums;

enum ListingStatus: string
{
    case Active = 'active';
    case Sold = 'sold';
    case Archived = 'archived';
}
