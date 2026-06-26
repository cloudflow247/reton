<?php

declare(strict_types=1);

namespace App\Domain\Recovery\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use DomainException;

final class RecoveryAlreadyOpenException extends DomainException implements RenderableApiException
{
    public static function forTransfer(string $transferId): self
    {
        return new self("An open recovery already exists for transfer [{$transferId}].");
    }

    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return 'recovery_already_open';
    }
}
