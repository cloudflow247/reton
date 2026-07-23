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
        $directionValue = $this->direction->value;

        return [
            'id' => $this->id,
            'direction' => $directionValue,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'created_at' => $this->created_at,
            'transaction' => $this->whenLoaded('transaction', function () {
                if ($this->transaction === null) {
                    return null;
                }

                try {
                    return (new TransactionResource($this->transaction))->resolve();
                } catch (\Throwable $e) {
                    report($e);

                    return [
                        'id' => $this->transaction->id,
                        'reference' => $this->transaction->reference,
                        'type' => $this->transaction->getRawOriginal('type'),
                        'status' => $this->transaction->getRawOriginal('status'),
                        'description' => $this->transaction->description,
                        'amount' => $this->transaction->amount,
                    ];
                }
            }),
        ];
    }
}
