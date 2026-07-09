<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Cards\Models\VirtualCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VirtualCard */
class VirtualCardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'scheme' => $this->scheme,
            'currency' => $this->currency,
            'brand' => $this->metadata['brand'] ?? 'Mastercard',
            'card_type' => 'virtual',
            'pan_masked' => $this->pan_masked,
            'pan_last4' => $this->pan_last4,
            'expiry_display' => $this->expiryDisplay(),
            'name_on_card' => $this->name_on_card,
            'is_blocked' => $this->status->isBlocked(),
            'card_balance_minor' => $this->metadata['card_balance_minor'] ?? null,
            'activated_at' => $this->activated_at?->toIso8601String(),
        ];
    }
}
