<?php

declare(strict_types=1);

namespace App\Domain\Callback\Models;

use App\Domain\Callback\Enums\CallbackResolution;
use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Transfers\Models\Transfer;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Callback extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'reference',
        'transfer_id',
        'initiated_by',
        'status',
        'reason',
        'resolution',
        'resolved_by',
        'responds_by',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'status' => CallbackStatus::class,
        'resolution' => CallbackResolution::class,
        'responds_by' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Transfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /** @return HasMany<CallbackEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(CallbackEvent::class)->latest('created_at');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
