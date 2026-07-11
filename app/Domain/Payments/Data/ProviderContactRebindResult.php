<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

/**
 * Outcome of rebinding an ALATPay/Wema contact email onto the CEO merchant inbox.
 */
final readonly class ProviderContactRebindResult
{
    public const STATUS_REBOUND = 'rebound';

    public const STATUS_ALREADY_OK = 'already_ok';

    public const STATUS_NEEDS_SUPPORT = 'needs_support';

    public const STATUS_MISSING_ACCOUNT = 'missing_account';

    public const STATUS_DRY_RUN = 'dry_run';

    public function __construct(
        public string $status,
        public string $userEmail,
        public ?string $accountNumber,
        public ?string $previousProviderEmail,
        public string $desiredProviderEmail,
        public string $message,
    ) {}

    public function ok(): bool
    {
        return in_array($this->status, [self::STATUS_REBOUND, self::STATUS_ALREADY_OK, self::STATUS_DRY_RUN], true);
    }
}
