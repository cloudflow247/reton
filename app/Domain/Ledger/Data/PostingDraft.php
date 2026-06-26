<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Data;

use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * A mutable builder describing a balanced posting before it is committed.
 *
 * The draft carries no balances of its own — it is validated and persisted by
 * the LedgerService, which is the sole authority over the ledger.
 */
final class PostingDraft
{
    /** @var list<EntryDraft> */
    private array $entries = [];

    private string $description = '';

    private ?string $idempotencyKey = null;

    private ?string $reference = null;

    private ?Model $initiator = null;

    /** @var array<string, mixed> */
    private array $metadata = [];

    private function __construct(public readonly TransactionType $type) {}

    public static function for(TransactionType $type): self
    {
        return new self($type);
    }

    public function describedAs(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function idempotentBy(?string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    public function referencedBy(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function initiatedBy(?Model $initiator): self
    {
        $this->initiator = $initiator;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    public function debit(LedgerAccount|string $account, Money $amount): self
    {
        $this->entries[] = new EntryDraft($account, EntryDirection::Debit, $amount);

        return $this;
    }

    public function credit(LedgerAccount|string $account, Money $amount): self
    {
        $this->entries[] = new EntryDraft($account, EntryDirection::Credit, $amount);

        return $this;
    }

    /** @return list<EntryDraft> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    public function initiator(): ?Model
    {
        return $this->initiator;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
