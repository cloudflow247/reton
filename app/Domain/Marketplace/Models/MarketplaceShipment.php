<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Models;

use App\Domain\Marketplace\Enums\HubVerificationStatus;
use App\Domain\Marketplace\Enums\ShipmentStatus;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceShipment extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'order_id',
        'carrier',
        'external_id',
        'tracking_number',
        'dropoff_code',
        'hub_name',
        'hub_address',
        'status',
        'hub_verification_status',
        'hub_verification_score',
        'hub_verification_report',
        'received_at_hub_at',
        'verified_at',
        'origin_address',
        'destination_address',
        'events',
        'estimated_delivery_at',
        'delivered_at',
        'pod_reference',
    ];

    protected $casts = [
        'status' => ShipmentStatus::class,
        'hub_verification_status' => HubVerificationStatus::class,
        'hub_address' => 'array',
        'hub_verification_report' => 'array',
        'origin_address' => 'array',
        'destination_address' => 'array',
        'events' => 'array',
        'estimated_delivery_at' => 'datetime',
        'received_at_hub_at' => 'datetime',
        'verified_at' => 'datetime',
        'delivered_at' => 'datetime',
        'hub_verification_score' => 'integer',
    ];

    /** @return BelongsTo<DigitalOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(DigitalOrder::class, 'order_id');
    }

    public function isDelivered(): bool
    {
        return $this->status === ShipmentStatus::Delivered;
    }

    public function isHubVerified(): bool
    {
        return $this->hub_verification_status === HubVerificationStatus::Passed;
    }
}
