<?php

declare(strict_types=1);

namespace App\Domain\Callback\Models;

use App\Domain\Callback\Enums\CallbackAction;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * An immutable entry on a callback's dispute timeline.
 */
class CallbackEvent extends Model
{
    use HasUuidKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'callback_id',
        'actor_type',
        'actor_id',
        'action',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'action' => CallbackAction::class,
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Callback events are immutable and cannot be updated.');
        });
    }

    /** @return BelongsTo<Callback, $this> */
    public function callback(): BelongsTo
    {
        return $this->belongsTo(Callback::class);
    }

    /** @return MorphTo<Model, $this> */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
