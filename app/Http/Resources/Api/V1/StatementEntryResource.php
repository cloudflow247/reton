<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Ledger\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single line on a wallet statement: the effect one transaction had on the
 * wallet's ledger account, with the originating transaction summarised.
 *
 * @mixin LedgerEntry
 */
class StatementEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'created_at' => $this->created_at,
            'transaction' => new TransactionResource($this->whenLoaded('transaction')),
        ];
    }
}
