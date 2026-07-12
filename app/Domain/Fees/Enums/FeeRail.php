<?php

declare(strict_types=1);

namespace App\Domain\Fees\Enums;

enum FeeRail: string
{
    case TransferInstant = 'transfer_instant';
    case TransferProtected = 'transfer_protected';
    case Withdraw = 'withdraw';
    case Deposit = 'deposit';
    case Callback = 'callback';
    case ListingPublish = 'listing_publish';
    case MarketplaceSale = 'marketplace_sale';
    case Recovery = 'recovery';
    case SmsAlert = 'sms_alert';

    public function label(): string
    {
        return match ($this) {
            self::TransferInstant => 'Instant transfer',
            self::TransferProtected => 'Protected transfer',
            self::Withdraw => 'Bank withdrawal',
            self::Deposit => 'Wallet deposit',
            self::Callback => 'Callback protection',
            self::ListingPublish => 'Listing publish',
            self::MarketplaceSale => 'Marketplace sale',
            self::Recovery => 'Wrong-transfer recovery',
            self::SmsAlert => 'SMS transaction alert',
        };
    }
}
