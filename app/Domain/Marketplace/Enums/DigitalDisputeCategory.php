<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Enums;

enum DigitalDisputeCategory: string
{
    case NotDelivered = 'not_delivered';
    case NotAsDescribed = 'not_as_described';
    case InvalidItem = 'invalid_item';

    public function label(): string
    {
        return match ($this) {
            self::NotDelivered => 'Item never delivered',
            self::NotAsDescribed => 'Does not match listing',
            self::InvalidItem => 'Key or link does not work',
        };
    }

    /** Buyer-facing hint shown in the dispute picker. */
    public function hint(): string
    {
        return match ($this) {
            self::NotDelivered => 'The seller has not delivered anything after the agreed window.',
            self::NotAsDescribed => 'What you received is different from what was advertised.',
            self::InvalidItem => 'The license key, code, or download link is broken or expired.',
        };
    }

    /**
     * @return list<self>
     */
    public static function forOrderStatus(DigitalOrderStatus $status): array
    {
        return match ($status) {
            DigitalOrderStatus::PaidHeld => [self::NotDelivered],
            DigitalOrderStatus::Delivered => [self::NotAsDescribed, self::InvalidItem],
            default => [],
        };
    }
}
