<?php

declare(strict_types=1);

namespace App\Domain\Callback\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use DomainException;

final class CallbackNotOpenException extends DomainException implements RenderableApiException
{
    public static function make(string $callbackId): self
    {
        return new self("Callback [{$callbackId}] is not open for this action.");
    }

    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return 'callback_not_open';
    }
}
