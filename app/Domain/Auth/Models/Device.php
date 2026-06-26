<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'user_id',
        'fingerprint',
        'name',
        'platform',
        'ip_address',
        'user_agent',
        'trusted',
        'last_seen_at',
    ];

    protected $casts = [
        'trusted' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
