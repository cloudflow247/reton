<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

enum DepositMethod: string
{
    case BankTransfer = 'bank_transfer';
    case AlatpayCheckout = 'alatpay_checkout';
    case AlatpayCard = 'alatpay_card';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Transfer from any bank',
            self::AlatpayCheckout => 'Pay with ALATPay',
            self::AlatpayCard => 'Pay with card',
        };
    }

    /** ALAT Pay channel code — null omits the field (all channels on hosted checkout). */
    public function alatpayChannel(): ?string
    {
        return match ($this) {
            self::BankTransfer, self::AlatpayCheckout => null,
            self::AlatpayCard => '1',
        };
    }
}
