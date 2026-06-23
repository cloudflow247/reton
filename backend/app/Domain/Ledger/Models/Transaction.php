<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'reference',
        'idempotency_key',
        'type',
        'status',
        'currency',
        'amount',
        'description',
        'metadata',
        'posted_at',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'status' => TransactionStatus::class,
        'amount' => 'integer',
        'metadata' => 'array',
        'posted_at' => 'datetime',
    ];

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** @return MorphTo<Model, $this> */
    public function initiator(): MorphTo
    {
        return $this->morphTo();
    }
}
