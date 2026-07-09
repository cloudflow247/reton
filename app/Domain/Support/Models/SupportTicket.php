<?php

declare(strict_types=1);

namespace App\Domain\Support\Models;

use App\Domain\Support\Enums\SupportTicketStatus;
use App\Domain\Transfers\Models\Transfer;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'reference',
        'user_id',
        'subject',
        'status',
        'transfer_id',
        'note',
        'metadata',
        'resolved_at',
    ];

    protected $casts = [
        'status' => SupportTicketStatus::class,
        'metadata' => 'array',
        'resolved_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Transfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /** @return HasMany<SupportMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
