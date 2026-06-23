<?php

declare(strict_types=1);

namespace App\Domain\Recovery\Models;

use App\Domain\Recovery\Enums\RecoveryResolution;
use App\Domain\Recovery\Enums\RecoveryStatus;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recovery extends Model
{
    use HasUuidKey;

    protected $table = 'recoveries';

    protected $fillable = [
        'reference',
        'transfer_id',
        'reported_by',
        'sender_wallet_id',
        'receiver_wallet_id',
        'status',
        'reason',
        'resolution',
        'amount',
        'fee',
        'currency',
        'resolved_by',
        'expires_at',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'status' => RecoveryStatus::class,
        'resolution' => RecoveryResolution::class,
        'amount' => 'integer',
        'fee' => 'integer',
        'expires_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Transfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function receiverWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'receiver_wallet_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function senderWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'sender_wallet_id');
    }

    /** @return HasMany<RecoveryEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(RecoveryEvent::class)->latest('created_at');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
