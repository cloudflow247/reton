<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use RuntimeException;

final class InvalidCredentialsException extends RuntimeException implements RenderableApiException
{
    public static function make(): self
    {
        return new self('The provided credentials are incorrect.');
    }

    public function apiStatus(): int
    {
        return 401;
    }

    public function apiCode(): string
    {
        return 'invalid_credentials';
    }
}
