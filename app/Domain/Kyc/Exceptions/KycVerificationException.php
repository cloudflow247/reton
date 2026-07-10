<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use RuntimeException;

class KycVerificationException extends RuntimeException implements RenderableApiException
{
    public function __construct(
        string $message,
        private readonly string $apiErrorCode = 'kyc_verification_failed',
        private readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function providerUnavailable(): self
    {
        return new self('Identity verification is temporarily unavailable. Please try again shortly.', 'kyc_provider_unavailable', 503);
    }

    public static function notFound(string $kind): self
    {
        return new self("We could not verify that {$kind}. Check the number and try again.", 'kyc_not_found', 422);
    }

    public function apiStatus(): int
    {
        return $this->status;
    }

    public function apiCode(): string
    {
        return $this->apiErrorCode;
    }
}
