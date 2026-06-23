<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Data;

use App\Domain\Fraud\Enums\FraudAction;
use App\Domain\Fraud\Enums\FraudRiskLevel;

final readonly class FraudAssessment
{
    /**
     * @param  list<FraudSignal>  $signals
     */
    public function __construct(
        public int $score,
        public FraudRiskLevel $level,
        public FraudAction $action,
        public array $signals,
    ) {}

    /** @return list<string> */
    public function reasons(): array
    {
        return array_map(fn (FraudSignal $signal) => $signal->code, $this->signals);
    }

    public function isFlagged(): bool
    {
        return $this->action !== FraudAction::Allow;
    }

    public function isBlocked(): bool
    {
        return $this->action->blocks();
    }
}
