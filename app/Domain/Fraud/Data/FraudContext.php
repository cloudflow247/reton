<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Data;

use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Money\Money;

/**
 * The signals available about a money movement at the moment it is assessed.
 *
 * This is the stable contract a future Go fraud service would receive; keeping
 * it a plain value object means swapping the scorer implementation requires no
 * caller changes.
 */
final readonly class FraudContext
{
    public function __construct(
        public User $user,
        public Wallet $wallet,
        public Money $amount,
        public string $action,
        public ?Wallet $beneficiary = null,
        public ?string $deviceFingerprint = null,
        public ?string $ipAddress = null,
    ) {}
}
