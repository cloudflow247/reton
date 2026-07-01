<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Transfers\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transfer
 */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'note' => $this->note,
            'sender_wallet_id' => $this->sender_wallet_id,
            'receiver_wallet_id' => $this->receiver_wallet_id,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'metadata' => $this->metadata,
            'hold' => new HoldResource($this->whenLoaded('hold')),
        ];
    }
}
