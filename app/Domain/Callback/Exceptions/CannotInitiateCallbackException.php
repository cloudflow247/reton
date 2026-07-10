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

    public static function reasonTooShort(int $minLength): self
    {
        return new self("Explain why you are recalling this payment (at least {$minLength} characters).");
    }

    public static function tooManyOpen(int $max): self
    {
        return new self("You already have {$max} open callbacks. Resolve one before raising another.");
    }

    public static function rateLimited(int $maxWeek): self
    {
        return new self("Fair-usage limit reached: at most {$maxWeek} callbacks per week.");
    }

    public static function abuseSuspected(): self
    {
        return new self('Callback Protection paused on this account for fair-usage review. Contact support if you need help.');
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
