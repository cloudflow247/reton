<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Marketplace\Models\DigitalOrder;
use App\Domain\Marketplace\Services\DigitalEscrowJudgementService;
use App\Domain\Marketplace\Services\DigitalMarketplaceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DigitalOrder
 */
class DigitalOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $marketplace = app(DigitalMarketplaceService::class);
        $escrow = app(DigitalEscrowJudgementService::class);
        $delivery = $viewer
            ? $marketplace->deliveryPayloadForBuyer($this->resource, $viewer)
            : null;

        $this->resource->loadMissing('shipment');

        return [
            'id' => $this->id,
            'listing_id' => $this->listing_id,
            'buyer_id' => $this->buyer_id,
            'seller_id' => $this->seller_id,
            'transfer_id' => $this->transfer_id,
            'status' => $this->status->value,
            'listing_snapshot' => $this->listing_snapshot,
            'verification_status' => $this->verification_status?->value,
            'verification_score' => $this->verification_score,
            'shipping_fee' => $this->shipping_fee,
            'shipped_at' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
            'completed_at' => $this->completed_at,
            'delivery_deadline_at' => $this->delivery_deadline_at,
            'buyer_satisfied' => $this->buyer_satisfied,
            'dispute_category' => $this->dispute_category,
            'created_at' => $this->created_at,
            'listing' => new DigitalListingResource($this->whenLoaded('listing')),
            'transfer' => new TransferResource($this->whenLoaded('transfer')),
            'delivery' => $delivery,
            'escrow' => $viewer ? $escrow->guidanceFor($this->resource, $viewer) : null,
            'role' => $viewer ? $this->roleFor($viewer->getKey()) : null,
        ];
    }

    private function roleFor(string $userId): ?string
    {
        if ($userId === (string) $this->buyer_id) {
            return 'buyer';
        }

        if ($userId === (string) $this->seller_id) {
            return 'seller';
        }

        return null;
    }
}
