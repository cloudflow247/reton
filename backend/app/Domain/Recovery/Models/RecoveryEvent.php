<?php

declare(strict_types=1);

namespace App\Domain\Recovery\Models;

use App\Domain\Recovery\Enums\RecoveryAction;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * An immutable entry on a recovery's timeline.
 */
class RecoveryEvent extends Model
{
    use HasUuidKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'recovery_id',
        'actor_type',
        'actor_id',
        'action',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'action' => RecoveryAction::class,
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Recovery events are immutable and cannot be updated.');
        });
    }

    /** @return BelongsTo<Recovery, $this> */
    public function recovery(): BelongsTo
    {
        return $this->belongsTo(Recovery::class);
    }

    /** @return MorphTo<Model, $this> */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
