<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Enums;

enum ItemType: string
{
    case Digital = 'digital';
    case Physical = 'physical';

    public function label(): string
    {
        return match ($this) {
            self::Digital => 'Digital item',
            self::Physical => 'Physical item',
        };
    }
}
