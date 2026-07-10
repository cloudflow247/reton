<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Payments\Enums\PayoutStatus;
use App\Domain\Payments\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ValueError;

/**
 * @mixin Payout
 */
class PayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'status' => $this->statusValue(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'bank_code' => $this->bank_code,
            'account_number' => $this->account_number,
            'account_name' => $this->account_name,
            'failure_reason' => $this->failure_reason,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function statusValue(): string
    {
        $raw = $this->resource->getRawOriginal('status');

        if ($raw === null || $raw === '') {
            return 'unknown';
        }

        if ($raw instanceof PayoutStatus) {
            return $raw->value;
        }

        try {
            return PayoutStatus::from((string) $raw)->value;
        } catch (ValueError) {
            return (string) $raw;
        }
    }
}
