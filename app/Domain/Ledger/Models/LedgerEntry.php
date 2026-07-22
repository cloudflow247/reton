<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\Enums\EntryDirection;
use App\Support\Concerns\HasUuidKey;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * An immutable double-entry accounting line.
 *
 * Once created, a ledger entry is a permanent fact. Any attempt to update or
 * delete a persisted entry is a programming error and is rejected outright -
 * corrections are made by posting a compensating (reversal) transaction.
 */
class LedgerEntry extends Model
{
    use HasUuidKey;

    public const UPDATED_AT = null;

    protected $fillable = [
        'transaction_id',
        'ledger_account_id',
        'direction',
        'amount',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'direction' => EntryDirection::class,
        'amount' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (LedgerEntry $entry): void {
            throw new RuntimeException('Ledger entries are immutable and cannot be updated.');
        });

        static::deleting(function (LedgerEntry $entry): void {
            throw new RuntimeException('Ledger entries are immutable and cannot be deleted.');
        });
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }
}
