<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\Ledger\Models\Transaction;
use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRequest extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'reference',
        'requester_user_id',
        'wallet_id',
        'provider',
        'provider_reference',
        'status',
        'amount',
        'currency',
        'title',
        'description',
        'payment_link_url',
        'payer_name',
        'payer_email',
        'transaction_id',
        'metadata',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'status' => PaymentRequestStatus::class,
        'amount' => 'integer',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
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

    public function isOpen(): bool
    {
        return $this->status === PaymentRequestStatus::Pending;
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentRequestStatus::Paid;
    }
}
