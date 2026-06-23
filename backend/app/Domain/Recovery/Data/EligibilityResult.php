<?php

declare(strict_types=1);

namespace App\Domain\Recovery\Data;

final readonly class EligibilityResult
{
    private function __construct(
        public bool $eligible,
        public ?string $reason = null,
    ) {}

    public static function eligible(): self
    {
        return new self(true);
    }

    public static function ineligible(string $reason): self
    {
        return new self(false, $reason);
    }
}
