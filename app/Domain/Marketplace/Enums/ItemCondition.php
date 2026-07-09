<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Enums;

enum ItemCondition: string
{
    case New = 'new';
    case LikeNew = 'like_new';
    case Good = 'good';
    case Fair = 'fair';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Brand new',
            self::LikeNew => 'Like new',
            self::Good => 'Good',
            self::Fair => 'Fair',
        };
    }
}
