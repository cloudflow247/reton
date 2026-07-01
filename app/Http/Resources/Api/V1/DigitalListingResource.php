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
            'seller_id' => $this->seller_id,
            'seller_name' => $this->whenLoaded('seller', fn () => $this->seller?->name),
            'title' => $this->title,
            'description' => $this->description,
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
