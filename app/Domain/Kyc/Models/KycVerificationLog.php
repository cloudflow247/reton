<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Models;

use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycVerificationLog extends Model
{
    use HasUuidKey;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'provider',
        'status',
        'failure_reason',
        'ip_address',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
