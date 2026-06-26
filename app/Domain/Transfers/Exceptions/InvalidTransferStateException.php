<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use DomainException;

final class InvalidTransferStateException extends DomainException implements RenderableApiException
{
    public static function notReleasable(string $transferId): self
    {
        return new self("Transfer [{$transferId}] is not in a state that can be released.");
    }

    public static function notRefundable(string $transferId): self
    {
        return new self("Transfer [{$transferId}] is not in a state that can be refunded.");
    }

    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return 'invalid_transfer_state';
    }
}
