<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\EntryDirection;
use App\Support\Concerns\HasUuidKey;
use App\Support\Money\Money;
use Database\Factories\LedgerAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerAccount extends Model
{
    /** @use HasFactory<LedgerAccountFactory> */
    use HasFactory;

    use HasUuidKey;

    protected $fillable = [
        'code',
        'name',
        'type',
        'currency',
        'is_system',
        'metadata',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'is_system' => 'boolean',
        'posted_debits' => 'integer',
        'posted_credits' => 'integer',
        'metadata' => 'array',
    ];

    protected static function newFactory(): LedgerAccountFactory
    {
        return LedgerAccountFactory::new();
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * Signed balance expressed on the account's natural side, in minor units.
     */
    public function balanceMinor(): int
    {
        return $this->type->normalBalance() === EntryDirection::Debit
            ? $this->posted_debits - $this->posted_credits
            : $this->posted_credits - $this->posted_debits;
    }

    public function balance(): Money
    {
        return Money::of($this->balanceMinor(), $this->currency);
    }
}
