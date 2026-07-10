<?php

declare(strict_types=1);

namespace App\Domain\Bills\Enums;

/**
 * The kinds of bill a customer can settle from their wallet.
 *
 * `Rrr` is the Remita Retrieval Reference flow (the payer looks up an RRR, whose
 * amount is fixed by the biller); the rest are amount-entered top-ups/utilities.
 */
enum BillCategory: string
{
    case Airtime = 'airtime';
    case Data = 'data';
    case Electricity = 'electricity';
    case CableTv = 'cable_tv';
    case Betting = 'betting';
    case Rrr = 'rrr';

    public function displayName(): string
    {
        return match ($this) {
            self::Airtime => 'Airtime',
            self::Data => 'Data',
            self::Electricity => 'Power',
            self::CableTv => 'TV',
            self::Betting => 'Betting',
            self::Rrr => 'Remita',
        };
    }

    /** Whether the payable amount is fixed by the biller (looked up), not entered by the payer. */
    public function hasFixedAmount(): bool
    {
        return $this === self::Rrr;
    }
}
