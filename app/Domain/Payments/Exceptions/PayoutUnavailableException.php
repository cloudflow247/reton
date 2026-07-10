<?php

declare(strict_types=1);

namespace App\Domain\Payments\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use RuntimeException;

/**
 * Outbound bank payouts are not available on the configured payment gateway.
 * Thrown before any ledger reservation so the user's balance is untouched.
 */
final class PayoutUnavailableException extends RuntimeException implements RenderableApiException
{
    public static function make(): self
    {
        return new self(
            'Bank withdrawals are not available yet. ALATPay is connected for deposits only — outbound payouts need Wema Debit Wallet access. Your balance was not charged.'
        );
    }

    public function apiStatus(): int
    {
        return 503;
    }

    public function apiCode(): string
    {
        return 'payout_unavailable';
    }
}
