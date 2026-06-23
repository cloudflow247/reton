<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use DomainException;

final class WalletCurrencyMismatchException extends DomainException implements RenderableApiException
{
    public static function between(string $walletCurrency, string $amountCurrency): self
    {
        return new self(
            "Operation currency {$amountCurrency} does not match wallet currency {$walletCurrency}."
        );
    }

    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return 'currency_mismatch';
    }
}
