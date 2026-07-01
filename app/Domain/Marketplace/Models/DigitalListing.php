<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Models;

use App\Domain\Marketplace\Enums\ListingStatus;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DigitalListing extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'seller_id',
        'title',
        'description',
        'price',
        'currency',
        'delivery_payload',
        'status',
    ];

    protected $casts = [
        'status' => ListingStatus::class,
        'price' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return HasMany<DigitalOrder, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(DigitalOrder::class, 'listing_id');
    }

    public function isActive(): bool
    {
        return $this->status === ListingStatus::Active;
    }
}
