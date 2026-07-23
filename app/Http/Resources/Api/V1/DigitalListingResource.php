<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Marketplace\Models\DigitalListing;
use App\Domain\Marketplace\Support\ListingLinks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DigitalListing
 */
class DigitalListingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_code' => $this->item_code,
            'seller_id' => $this->seller_id,
            'seller_name' => $this->whenLoaded('seller', fn () => $this->seller?->name),
            'item_type' => $this->item_type->value,
            'title' => $this->title,
            'description' => $this->description,
            'condition' => $this->condition?->value,
            'condition_label' => $this->condition?->label(),
            'weight_grams' => $this->weight_grams,
            'specs' => $this->specs,
            'handling_notes' => $this->handling_notes,
            'verification_status' => $this->verification_status?->value,
            'verification_score' => $this->verification_score,
            'price' => $this->price,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'share_url' => ListingLinks::web($this->resource),
            'app_url' => ListingLinks::app($this->resource),
            'is_owner' => $request->user() !== null
                && (string) $request->user()->getKey() === (string) $this->seller_id,
            'can_purchase' => $request->user()?->can('purchase', $this->resource) ?? false,
        ];
    }
}
