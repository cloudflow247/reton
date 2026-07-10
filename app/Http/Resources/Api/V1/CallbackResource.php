<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Callback\Models\Callback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Callback
 */
class CallbackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fairness = is_array($this->metadata['fairness'] ?? null)
            ? $this->metadata['fairness']
            : null;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'transfer_id' => $this->transfer_id,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'resolution' => $this->resolution?->value,
            'responds_by' => $this->responds_by,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'fairness' => $fairness,
            'events' => CallbackEventResource::collection($this->whenLoaded('events')),
        ];
    }
}
