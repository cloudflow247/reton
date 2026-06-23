<?php

declare(strict_types=1);

namespace App\Domain\Recovery\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use DomainException;

final class CannotReportRecoveryException extends DomainException implements RenderableApiException
{
    public static function notNormalCompleted(): self
    {
        return new self('Recovery can only be reported on a completed normal transfer.');
    }

    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return 'recovery_not_allowed';
    }
}
