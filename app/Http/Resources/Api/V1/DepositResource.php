<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Payments\Models\Deposit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Deposit
 */
class DepositResource extends JsonResource
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
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->metadata['method'] ?? 'bank_transfer',
            'virtual_account' => $this->virtual_account,
            'payment_link_url' => $this->metadata['payment_link_url'] ?? null,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
