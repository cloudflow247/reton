<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Models;

use App\Domain\Fraud\Enums\FraudAction;
use App\Domain\Fraud\Enums\FraudRiskLevel;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FraudAlert extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'action_context',
        'score',
        'level',
        'recommended_action',
        'signals',
        'amount',
        'currency',
        'status',
        'resolved_by',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'score' => 'integer',
        'level' => FraudRiskLevel::class,
        'recommended_action' => FraudAction::class,
        'signals' => 'array',
        'amount' => 'integer',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
