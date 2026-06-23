<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Recovery\Models\Recovery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Recovery
 */
class RecoveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'transfer_id' => $this->transfer_id,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'resolution' => $this->resolution?->value,
            'amount' => $this->amount,
            'fee' => $this->fee,
            'currency' => $this->currency,
            'expires_at' => $this->expires_at,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'events' => RecoveryEventResource::collection($this->whenLoaded('events')),
        ];
    }
}
