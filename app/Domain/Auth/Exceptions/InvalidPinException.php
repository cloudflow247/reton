<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use RuntimeException;

final class InvalidPinException extends RuntimeException implements RenderableApiException
{
    public static function make(): self
    {
        return new self('The transaction PIN is incorrect.');
    }

    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return 'invalid_pin';
    }
}
