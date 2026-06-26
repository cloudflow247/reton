<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use RuntimeException;

final class PinNotSetException extends RuntimeException implements RenderableApiException
{
    public static function make(): self
    {
        return new self('No transaction PIN has been set for this account.');
    }

    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return 'pin_not_set';
    }
}
