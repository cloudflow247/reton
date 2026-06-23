<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Models;

use App\Domain\Ledger\Models\Transaction;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Enums\TransferType;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transfer extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'reference',
        'sender_wallet_id',
        'receiver_wallet_id',
        'initiated_by',
        'type',
        'status',
        'currency',
        'amount',
        'note',
        'transaction_id',
        'idempotency_key',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'type' => TransferType::class,
        'status' => TransferStatus::class,
        'amount' => 'integer',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<Wallet, $this> */
    public function senderWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'sender_wallet_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function receiverWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'receiver_wallet_id');
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return HasOne<Hold, $this> */
    public function hold(): HasOne
    {
        return $this->hasOne(Hold::class);
    }

    public function isProtected(): bool
    {
        return $this->type === TransferType::Protected;
    }
}
