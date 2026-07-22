<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Enums;

enum ShipmentStatus: string
{
    case AwaitingDropoff = 'awaiting_dropoff';
    case AtHub = 'at_hub';
    case Verifying = 'verifying';
    case VerificationPassed = 'verification_passed';
    case VerificationFailed = 'verification_failed';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Failed = 'failed';

    /** @deprecated Use AwaitingDropoff - kept for legacy rows */
    case PendingPickup = 'pending_pickup';

    /** @deprecated Use InTransit */
    case PickedUp = 'picked_up';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingDropoff, self::PendingPickup => 'Take item to Giglogistics hub',
            self::AtHub => 'Received at verification hub',
            self::Verifying => 'Giglogistics verifying item',
            self::VerificationPassed => 'Verified - shipping to buyer',
            self::VerificationFailed => 'Did not pass hub verification',
            self::InTransit, self::PickedUp => 'In transit to buyer',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered to buyer',
            self::Failed => 'Delivery failed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Failed, self::VerificationFailed], true);
    }

    public function isPreTransit(): bool
    {
        return in_array($this, [
            self::AwaitingDropoff,
            self::PendingPickup,
            self::AtHub,
            self::Verifying,
            self::VerificationPassed,
        ], true);
    }
}
