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
        $provider = (string) config('reton.payouts.provider', 'paystack');

        $message = match ($provider) {
            'paystack' => 'Bank withdrawals are unavailable. Add a Paystack secret key in Admin → Integrations (or switch driver to demo). Your balance was not charged.',
            'alatpay' => 'Bank withdrawals are unavailable. ALATPay Debit Wallet is not enabled. Your balance was not charged.',
            default => 'Bank withdrawals are unavailable on the configured payout provider. Your balance was not charged.',
        };

        return new self($message);
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
