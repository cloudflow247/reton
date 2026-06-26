<?php

declare(strict_types=1);

namespace App\Domain\Callback\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use DomainException;

final class CallbackAlreadyOpenException extends DomainException implements RenderableApiException
{
    public static function forTransfer(string $transferId): self
    {
        return new self("An open callback already exists for transfer [{$transferId}].");
    }

    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return 'callback_already_open';
    }
}
