<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Models;

use App\Domain\Transfers\Enums\HoldStatus;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hold extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'transfer_id',
        'amount',
        'currency',
        'status',
        'reason',
        'expires_at',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'status' => HoldStatus::class,
        'amount' => 'integer',
        'expires_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Transfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function isActive(): bool
    {
        return $this->status === HoldStatus::Active;
    }
}
