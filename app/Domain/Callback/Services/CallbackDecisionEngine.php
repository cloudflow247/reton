<?php

declare(strict_types=1);

namespace App\Domain\Callback\Services;

use App\Domain\Callback\Enums\CallbackResolution;
use App\Domain\Callback\Models\Callback;
use App\Domain\Marketplace\Models\DigitalOrder;
use App\Domain\Marketplace\Services\DigitalEscrowJudgementService;

/**
 * Decides the outcome of a callback for the cases that resolve automatically
 * (without an explicit admin decision).
 *
 * Marketplace digital orders use escrow judgement. P2P protected transfers use
 * {@see ProtectionFairnessService} so both sender and receiver are scored.
 */
class CallbackDecisionEngine
{
    public function __construct(
        private readonly DigitalEscrowJudgementService $digitalEscrow,
        private readonly ProtectionFairnessService $fairness,
    ) {}

    /**
     * Outcome when a callback expires with no resolution.
     */
    public function resolveOnExpiry(Callback $callback): CallbackResolution
    {
        $order = DigitalOrder::query()->where('transfer_id', $callback->transfer_id)->first();

        if ($order instanceof DigitalOrder) {
            return $this->digitalEscrow->resolveOnCallbackExpiry($callback, $order);
        }

        $assessment = $this->fairness->assessExpiry($callback);

        $callback->update([
            'metadata' => array_merge((array) ($callback->metadata ?? []), [
                'fairness' => $assessment->toArray(),
                'fairness_decided_at' => now()->toIso8601String(),
            ]),
        ]);

        return $assessment->resolution;
    }
}
