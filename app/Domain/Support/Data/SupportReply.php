<?php

declare(strict_types=1);

namespace App\Domain\Support\Data;

/**
 * Structured assistant response for the in-app support chat.
 *
 * @phpstan-type SupportAction array{label: string, href: string}
 */
final readonly class SupportReply
{
    /**
     * @param  list<SupportAction>  $actions
     */
    public function __construct(
        public string $body,
        public array $actions = [],
        public ?array $metadata = null,
    ) {}
}
