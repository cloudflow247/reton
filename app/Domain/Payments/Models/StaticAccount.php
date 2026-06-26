<?php

declare(strict_types=1);

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaticAccount extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'provider',
        'provider_reference',
        'wallet_type',
        'status',
        'account_number',
        'account_name',
        'bank_name',
        'otp_tracking_id',
        'email',
        'last_polled_at',
        'metadata',
    ];

    protected $casts = [
        'wallet_type' => StaticWalletType::class,
        'status' => StaticAccountStatus::class,
        'last_polled_at' => 'datetime',
        'metadata' => 'array',
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

    public function isActive(): bool
    {
        return $this->status === StaticAccountStatus::Active;
    }
}
