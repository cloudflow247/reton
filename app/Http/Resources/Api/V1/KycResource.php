<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Kyc\Models\UserKyc;
use App\Domain\Kyc\Services\KycService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserKyc
 */
class KycResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $limits = app(KycService::class)->limitsFor($this->resource);

        return [
            'tier' => $this->tier->value,
            'tier_label' => $this->tier->label(),
            'bvn_verified' => $this->bvn_verified_at !== null,
            'bvn_last4' => $this->bvn_last4,
            'nin_verified' => $this->nin_verified_at !== null,
            'nin_last4' => $this->nin_last4,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'address_line1' => $this->address_line1,
            'city' => $this->city,
            'state' => $this->state,
            'limits' => [
                'single_transaction_max' => $limits['single_transaction_max'],
                'daily_inflow_max' => $limits['daily_inflow_max'],
                'wallet_balance_max' => $limits['wallet_balance_max'],
                'static_wallet_type' => $limits['static_wallet_type'],
            ],
            'next_tier' => match ($this->tier->value) {
                1 => 2,
                2 => 3,
                default => null,
            },
        ];
    }
}
