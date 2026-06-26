<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Implemented by domain exceptions that represent an expected, user-facing
 * business outcome (e.g. insufficient funds) rather than a programming fault.
 *
 * The API exception handler renders these as a clean error envelope using the
 * status and machine-readable code reported here; everything else is treated
 * as an internal error.
 */
interface RenderableApiException
{
    public function apiStatus(): int;

    public function apiCode(): string;
}
