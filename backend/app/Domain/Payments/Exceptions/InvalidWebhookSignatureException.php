<?php

declare(strict_types=1);

namespace App\Domain\Payments\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use RuntimeException;

final class InvalidWebhookSignatureException extends RuntimeException implements RenderableApiException
{
    public static function make(): self
    {
        return new self('The webhook signature could not be verified.');
    }

    public function apiStatus(): int
    {
        return 401;
    }

    public function apiCode(): string
    {
        return 'invalid_signature';
    }
}
