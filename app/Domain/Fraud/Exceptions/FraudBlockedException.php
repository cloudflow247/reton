<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use RuntimeException;

final class FraudBlockedException extends RuntimeException implements RenderableApiException
{
    public static function make(): self
    {
        return new self('This transaction was blocked for your security. Please contact support.');
    }

    public function apiStatus(): int
    {
        return 403;
    }

    public function apiCode(): string
    {
        return 'fraud_blocked';
    }
}
