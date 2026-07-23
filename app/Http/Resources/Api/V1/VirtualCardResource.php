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
        if (! $this->resource instanceof VirtualCard) {
            return [];
        }

        $card = $this->resource;
        $metadata = is_array($card->metadata) ? $card->metadata : [];

        return [
            'id' => $card->id,
            'status' => $card->status->value,
            'scheme' => $card->scheme,
            'currency' => $card->currency,
            'brand' => (string) ($metadata['brand'] ?? 'Mastercard'),
            'card_type' => 'virtual',
            'pan_masked' => $card->pan_masked,
            'pan_last4' => $card->pan_last4,
            'expiry_display' => $card->expiryDisplay(),
            'name_on_card' => $card->name_on_card,
            'is_blocked' => $card->status->isBlocked(),
            'card_balance_minor' => $metadata['card_balance_minor'] ?? null,
            'activated_at' => $card->activated_at?->toIso8601String(),
        ];
    }
}
