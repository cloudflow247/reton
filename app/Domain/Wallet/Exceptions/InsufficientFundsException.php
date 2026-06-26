<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use App\Support\Money\Money;
use DomainException;

final class InsufficientFundsException extends DomainException implements RenderableApiException
{
    public static function for(string $walletId, Money $available, Money $requested): self
    {
        return new self(
            "Wallet [{$walletId}] has {$available} available but {$requested} was requested."
        );
    }

    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return 'insufficient_funds';
    }
}
