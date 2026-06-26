<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Data;

/**
 * One triggered fraud indicator and the risk points it contributes.
 */
final readonly class FraudSignal
{
    public function __construct(
        public string $code,
        public int $points,
        public string $description,
    ) {}
}
