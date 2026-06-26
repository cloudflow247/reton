<?php

declare(strict_types=1);

namespace App\Domain\Recovery\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use DomainException;

final class RecoveryNotOpenException extends DomainException implements RenderableApiException
{
    public static function make(string $recoveryId): self
    {
        return new self("Recovery [{$recoveryId}] is not open for this action.");
    }

    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return 'recovery_not_open';
    }
}
