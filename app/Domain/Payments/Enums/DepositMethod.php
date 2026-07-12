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

    /** Platform feature flag key under `reton.features.*`. */
    public function featureKey(): string
    {
        return match ($this) {
            self::AlatpayCheckout => 'checkout',
            self::AlatpayCard => 'card_pay',
            self::BankTransfer => 'one_time',
        };
    }

    public function isEnabled(): bool
    {
        return (bool) config('reton.features.'.$this->featureKey(), false);
    }

    /**
     * @return list<self>
     */
    public static function enabledMethods(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $method): bool => $method->isEnabled(),
        ));
    }
}
