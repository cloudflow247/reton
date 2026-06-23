<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasUuidKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'provider',
        'event_id',
        'type',
        'signature_valid',
        'status',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'payload' => 'array',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
