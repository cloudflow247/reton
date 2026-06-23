<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\Ledger\Models\Transaction;
use App\Domain\Payments\Enums\DepositStatus;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'reference',
        'user_id',
        'wallet_id',
        'provider',
        'provider_reference',
        'status',
        'amount',
        'currency',
        'virtual_account',
        'transaction_id',
        'metadata',
        'paid_at',
    ];

    protected $casts = [
        'status' => DepositStatus::class,
        'amount' => 'integer',
        'virtual_account' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === DepositStatus::Completed;
    }
}
