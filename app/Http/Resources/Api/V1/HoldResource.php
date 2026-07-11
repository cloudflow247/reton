<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Transfers\Models\Hold;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Hold
 */
class HoldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return [];
        }

        $status = $this->status;

        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => is_object($status) && isset($status->value)
                ? $status->value
                : (string) ($this->resource->getRawOriginal('status') ?? ''),
            'reason' => $this->reason,
            'expires_at' => $this->expires_at,
            'resolved_at' => $this->resolved_at,
        ];
    }
}
