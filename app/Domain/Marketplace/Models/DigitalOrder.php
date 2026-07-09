<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Models;

use App\Domain\Marketplace\Enums\DigitalOrderStatus;
use App\Domain\Marketplace\Enums\ItemType;
use App\Domain\Marketplace\Enums\VerificationStatus;
use App\Domain\Transfers\Models\Transfer;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DigitalOrder extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'listing_id',
        'buyer_id',
        'seller_id',
        'transfer_id',
        'status',
        'listing_snapshot',
        'buyer_accepted_description_at',
        'verification_status',
        'verification_score',
        'shipping_address',
        'shipping_fee',
        'delivered_at',
        'shipped_at',
        'received_at',
        'completed_at',
        'delivery_deadline_at',
        'seller_attested_at',
        'payload_checksum',
        'buyer_reviewed_at',
        'buyer_satisfied',
        'dispute_category',
    ];

    protected $casts = [
        'status' => DigitalOrderStatus::class,
        'listing_snapshot' => 'array',
        'verification_status' => VerificationStatus::class,
        'shipping_address' => 'array',
        'shipping_fee' => 'integer',
        'verification_score' => 'integer',
        'delivered_at' => 'datetime',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'completed_at' => 'datetime',
        'delivery_deadline_at' => 'datetime',
        'seller_attested_at' => 'datetime',
        'buyer_reviewed_at' => 'datetime',
        'buyer_accepted_description_at' => 'datetime',
        'buyer_satisfied' => 'boolean',
    ];

    /** @return BelongsTo<DigitalListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(DigitalListing::class, 'listing_id');
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<Transfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /** @return HasOne<MarketplaceShipment, $this> */
    public function shipment(): HasOne
    {
        return $this->hasOne(MarketplaceShipment::class, 'order_id');
    }

    public function isPhysical(): bool
    {
        $type = $this->listing_snapshot['item_type'] ?? $this->listing?->item_type?->value ?? ItemType::Digital->value;

        return $type === ItemType::Physical->value;
    }

    public function isDelivered(): bool
    {
        return in_array($this->status, [
            DigitalOrderStatus::Delivered,
            DigitalOrderStatus::Completed,
        ], true);
    }

    public function isInTransit(): bool
    {
        return $this->status === DigitalOrderStatus::Shipped;
    }
}
