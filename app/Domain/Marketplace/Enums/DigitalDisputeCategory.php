<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Enums;

enum DigitalDisputeCategory: string
{
    case NotDelivered = 'not_delivered';
    case NotAsDescribed = 'not_as_described';
    case InvalidItem = 'invalid_item';
    case DamagedInTransit = 'damaged_in_transit';
    case WrongItem = 'wrong_item';

    public function label(): string
    {
        return match ($this) {
            self::NotDelivered => 'Item never delivered',
            self::NotAsDescribed => 'Does not match listing',
            self::InvalidItem => 'Key or link does not work',
            self::DamagedInTransit => 'Damaged in transit',
            self::WrongItem => 'Wrong item received',
        };
    }

    /** Buyer-facing hint shown in the dispute picker. */
    public function hint(): string
    {
        return match ($this) {
            self::NotDelivered => 'The seller has not delivered anything after the agreed window.',
            self::NotAsDescribed => 'What you received is different from what was advertised.',
            self::InvalidItem => 'The license key, code, or download link is broken or expired.',
            self::DamagedInTransit => 'The package arrived damaged or with missing parts.',
            self::WrongItem => 'You received a different product than what was ordered.',
        };
    }

    /**
     * @return list<self>
     */
    public static function forOrderStatus(DigitalOrderStatus $status, ?ItemType $itemType = null): array
    {
        $physical = $itemType === ItemType::Physical;

        return match ($status) {
            DigitalOrderStatus::PaidHeld => [self::NotDelivered],
            DigitalOrderStatus::AwaitingVerification => [self::NotDelivered],
            DigitalOrderStatus::Shipped => $physical ? [self::NotDelivered] : [],
            DigitalOrderStatus::Delivered => $physical
                ? [self::NotAsDescribed, self::DamagedInTransit, self::WrongItem]
                : [self::NotAsDescribed, self::InvalidItem],
            default => [],
        };
    }
}
