<?php

declare(strict_types=1);

namespace App\Domain\Auth\Data;

use Illuminate\Http\Request;

/**
 * Device fingerprint captured from a request's headers, used to recognise the
 * hardware a user authenticates from for fraud and risk scoring.
 */
final readonly class DeviceContext
{
    public function __construct(
        public string $fingerprint,
        public ?string $name = null,
        public ?string $platform = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}

    public static function fromRequest(Request $request): ?self
    {
        $fingerprint = $request->header('X-Device-Fingerprint');

        if (! is_string($fingerprint) || $fingerprint === '') {
            return null;
        }

        return new self(
            fingerprint: $fingerprint,
            name: $request->header('X-Device-Name'),
            platform: $request->header('X-Device-Platform'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
