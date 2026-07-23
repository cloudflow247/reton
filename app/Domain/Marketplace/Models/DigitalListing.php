<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Models;

use App\Domain\Marketplace\Enums\ItemCondition;
use App\Domain\Marketplace\Enums\ItemType;
use App\Domain\Marketplace\Enums\ListingStatus;
use App\Domain\Marketplace\Enums\VerificationStatus;
use App\Domain\Marketplace\Support\ListingItemCodes;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DigitalListing extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'item_code',
        'seller_id',
        'item_type',
        'title',
        'description',
        'condition',
        'weight_grams',
        'dimensions_cm',
        'specs',
        'handling_notes',
        'verification_status',
        'verification_score',
        'price',
        'currency',
        'delivery_payload',
        'status',
    ];

    protected $casts = [
        'item_type' => ItemType::class,
        'condition' => ItemCondition::class,
        'dimensions_cm' => 'array',
        'specs' => 'array',
        'verification_status' => VerificationStatus::class,
        'status' => ListingStatus::class,
        'price' => 'integer',
        'weight_grams' => 'integer',
        'verification_score' => 'integer',
    ];

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $code = ListingItemCodes::normalize((string) $value);

        return $query->where(function (Builder $builder) use ($value, $code): void {
            $builder->where('id', $value);

            if ($code !== null) {
                $builder->orWhere('item_code', $code);
            }
        });
    }

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

    public function isPhysical(): bool
    {
        return $this->item_type === ItemType::Physical;
    }

    public function isDigital(): bool
    {
        return $this->item_type !== ItemType::Physical;
    }

    /** @return array<string, mixed> */
    public function toSnapshot(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'item_type' => ($this->item_type ?? ItemType::Digital)->value,
            'condition' => $this->condition?->value,
            'weight_grams' => $this->weight_grams,
            'dimensions_cm' => $this->dimensions_cm,
            'specs' => $this->specs,
            'handling_notes' => $this->handling_notes,
            'price' => $this->price,
            'currency' => $this->currency,
            'checksum' => hash('sha256', json_encode([
                $this->title,
                $this->description,
                $this->condition?->value,
                $this->specs,
                $this->weight_grams,
            ], JSON_THROW_ON_ERROR)),
        ];
    }
}
