<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\PayoutStatus;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
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
        'bank_code',
        'account_number',
        'account_name',
        'reservation_transaction_id',
        'settlement_transaction_id',
        'failure_reason',
        'metadata',
        'processed_at',
    ];

    protected $casts = [
        'status' => PayoutStatus::class,
        'amount' => 'integer',
        'metadata' => 'array',
        'processed_at' => 'datetime',
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

    public function isPending(): bool
    {
        return $this->status === PayoutStatus::Pending;
    }
}
