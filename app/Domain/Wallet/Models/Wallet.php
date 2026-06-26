<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Models;

use App\Domain\Ledger\Models\LedgerAccount;
use App\Support\Concerns\HasUuidKey;
use App\Support\Money\Money;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    use HasUuidKey;

    protected $fillable = [
        'ledger_account_id',
        'currency',
        'status',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'integer',
        'held_balance' => 'integer',
        'metadata' => 'array',
    ];

    protected static function newFactory(): WalletFactory
    {
        return WalletFactory::new();
    }

    protected static function booted(): void
    {
        // Every wallet gets a short, unique, shareable account number.
        static::creating(function (Wallet $wallet): void {
            if (empty($wallet->account_number)) {
                $wallet->account_number = self::generateAccountNumber();
            }
        });
    }

    private static function generateAccountNumber(): string
    {
        do {
            $number = (string) random_int(1_000_000_000, 9_999_999_999);
        } while (self::where('account_number', $number)->exists());

        return $number;
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** Funds the wallet holds but cannot spend (callback / recovery holds). */
    public function availableMinor(): int
    {
        return $this->balance - $this->held_balance;
    }

    public function available(): Money
    {
        return Money::of($this->availableMinor(), $this->currency);
    }
}
