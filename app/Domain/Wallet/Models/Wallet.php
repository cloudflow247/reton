<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Models;

use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Wallet\Support\RetonId;
use App\Support\Concerns\HasUuidKey;
use App\Support\Money\Money;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        // Every wallet gets a unique RETON ID (R + 9 digits with Luhn checksum).
        static::creating(function (Wallet $wallet): void {
            if (empty($wallet->account_number)) {
                $wallet->account_number = self::generateRetonId();
            }
        });
    }

    public static function generateRetonId(): string
    {
        do {
            $id = RetonId::generate();
        } while (self::where('account_number', $id)->exists());

        return $id;
    }

    /**
     * Re-issue legacy numeric wallet numbers as RETON IDs. Safe to run repeatedly.
     */
    public static function reissueLegacyAccountNumbers(): int
    {
        $updated = 0;

        self::query()
            ->orderBy('created_at')
            ->each(function (Wallet $wallet) use (&$updated): void {
                $current = (string) ($wallet->account_number ?? '');

                if (RetonId::isValid($current)) {
                    $normalized = RetonId::normalize($current);

                    if ($normalized !== null && $normalized !== $current) {
                        $wallet->forceFill(['account_number' => $normalized])->saveQuietly();
                        $updated++;
                    }

                    return;
                }

                $meta = is_array($wallet->metadata) ? $wallet->metadata : [];

                if ($current !== '') {
                    $meta['legacy_account_number'] = $current;
                }

                $wallet->forceFill([
                    'account_number' => self::generateRetonId(),
                    'metadata' => $meta,
                ])->saveQuietly();

                $updated++;
            });

        return $updated;
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

    /** @return HasOne<StaticAccount, $this> */
    public function staticAccount(): HasOne
    {
        return $this->hasOne(StaticAccount::class)->latestOfMany();
    }

    /**
     * Spendable funds = ledger balance minus escrow holds.
     * Never report negative available (defensive clamp for fintech safety).
     */
    public function availableMinor(): int
    {
        return max(0, (int) $this->balance - (int) $this->held_balance);
    }

    /**
     * Escrow / protected / recovery holds that count toward ledger balance
     * but cannot be spent until released.
     */
    public function heldMinor(): int
    {
        return max(0, (int) $this->held_balance);
    }

    /**
     * Full ledger liability balance (available + escrow).
     */
    public function ledgerMinor(): int
    {
        return max(0, (int) $this->balance);
    }

    public function available(): Money
    {
        return Money::of($this->availableMinor(), $this->currency);
    }
}
