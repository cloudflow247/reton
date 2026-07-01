<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Models;

use App\Domain\Marketplace\Enums\DigitalOrderStatus;
use App\Domain\Transfers\Models\Transfer;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalOrder extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'listing_id',
        'buyer_id',
        'seller_id',
        'transfer_id',
        'status',
        'delivered_at',
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
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'delivery_deadline_at' => 'datetime',
        'seller_attested_at' => 'datetime',
        'buyer_reviewed_at' => 'datetime',
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

    public function isDelivered(): bool
    {
        return in_array($this->status, [
            DigitalOrderStatus::Delivered,
            DigitalOrderStatus::Completed,
        ], true);
    }
}
