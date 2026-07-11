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

        $type = $this->type;
        $status = $this->status;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => is_object($type) && isset($type->value)
                ? $type->value
                : (string) ($this->resource->getRawOriginal('type') ?? ''),
            'status' => is_object($status) && isset($status->value)
                ? $status->value
                : (string) ($this->resource->getRawOriginal('status') ?? ''),
            'currency' => $this->currency,
            'amount' => $this->amount,
            'description' => $this->description,
            'posted_at' => $this->posted_at,
            'created_at' => $this->created_at,
        ];
    }
}
