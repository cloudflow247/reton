<?php

declare(strict_types=1);

namespace App\Domain\Support\Models;

use App\Domain\Support\Enums\SupportMessageRole;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'user_id',
        'ticket_id',
        'role',
        'body',
        'actions',
        'metadata',
    ];

    protected $casts = [
        'role' => SupportMessageRole::class,
        'actions' => 'array',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<SupportTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }
}
