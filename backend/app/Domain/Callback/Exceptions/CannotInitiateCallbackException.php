<?php

declare(strict_types=1);

namespace App\Domain\Callback\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use DomainException;

final class CannotInitiateCallbackException extends DomainException implements RenderableApiException
{
    public static function notProtectedAndHeld(): self
    {
        return new self('A callback can only be raised on a protected transfer whose funds are still held.');
    }

    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return 'callback_not_allowed';
    }
}
