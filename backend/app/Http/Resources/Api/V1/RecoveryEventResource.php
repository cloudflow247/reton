<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Recovery\Models\RecoveryEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin RecoveryEvent
 */
class RecoveryEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'notes' => $this->notes,
            'actor_type' => $this->actor_type !== null ? Str::lower(class_basename($this->actor_type)) : 'system',
            'actor_id' => $this->actor_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
