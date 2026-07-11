<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * Resolves client-supplied idempotency keys for money-movement endpoints.
 *
 * Prefer the Idempotency-Key header (API / Inertia). Fall back to a body field
 * for clients that cannot set custom headers.
 */
final class IdempotencyKey
{
    public static function from(Request $request): ?string
    {
        $header = $request->header('Idempotency-Key');
        if (is_string($header) && $header !== '') {
            return mb_substr($header, 0, 128);
        }

        $body = $request->input('idempotency_key');
        if (is_string($body) && $body !== '') {
            return mb_substr($body, 0, 128);
        }

        return null;
    }
}
