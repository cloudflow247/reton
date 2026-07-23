<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Ledger\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return [];
        }

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'description' => $this->description,
            'posted_at' => $this->posted_at,
            'created_at' => $this->created_at,
        ];
    }
}
