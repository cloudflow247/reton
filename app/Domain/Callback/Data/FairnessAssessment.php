<?php

declare(strict_types=1);

namespace App\Domain\Callback\Data;

use App\Domain\Callback\Enums\CallbackResolution;

/**
 * Explainable fairness judgement for a protected-transfer callback.
 *
 * @phpstan-type FairnessArray array{
 *     sender_score: int,
 *     receiver_score: int,
 *     category: string,
 *     evidence_score: int|null,
 *     resolution: string,
 *     reasons: list<string>,
 *     hold_hours: int|null,
 *     response_hours: int|null
 * }
 */
final readonly class FairnessAssessment
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public int $senderScore,
        public int $receiverScore,
        public string $category,
        public CallbackResolution $resolution,
        public array $reasons,
        public ?int $evidenceScore = null,
        public ?int $holdHours = null,
        public ?int $responseHours = null,
    ) {}

    /**
     * @return FairnessArray
     */
    public function toArray(): array
    {
        return [
            'sender_score' => $this->senderScore,
            'receiver_score' => $this->receiverScore,
            'category' => $this->category,
            'evidence_score' => $this->evidenceScore,
            'resolution' => $this->resolution->value,
            'reasons' => $this->reasons,
            'hold_hours' => $this->holdHours,
            'response_hours' => $this->responseHours,
        ];
    }
}
