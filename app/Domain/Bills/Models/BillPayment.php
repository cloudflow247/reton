<?php

declare(strict_types=1);

namespace App\Domain\Bills\Models;

use App\Domain\Bills\Enums\BillCategory;
use App\Domain\Bills\Enums\BillStatus;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillPayment extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'reference',
        'user_id',
        'wallet_id',
        'provider',
        'provider_reference',
        'status',
        'category',
        'biller_code',
        'biller_name',
        'customer_reference',
        'amount',
        'currency',
        'reservation_transaction_id',
        'settlement_transaction_id',
        'failure_reason',
        'metadata',
        'processed_at',
    ];

    protected $casts = [
        'status' => BillStatus::class,
        'category' => BillCategory::class,
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
        return $this->status === BillStatus::Pending;
    }
}
