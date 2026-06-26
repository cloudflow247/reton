<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use RuntimeException;

final class PinLockedException extends RuntimeException implements RenderableApiException
{
    public static function make(): self
    {
        return new self('Your transaction PIN is temporarily locked due to too many failed attempts.');
    }

    public function apiStatus(): int
    {
        return 423;
    }

    public function apiCode(): string
    {
        return 'pin_locked';
    }
}
