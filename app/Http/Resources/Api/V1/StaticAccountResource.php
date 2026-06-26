<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Payments\Models\StaticAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaticAccount
 */
class StaticAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wallet_id' => $this->wallet_id,
            'wallet_type' => $this->wallet_type->value,
            'status' => $this->status->value,
            'account_number' => $this->account_number,
            'account_name' => $this->account_name,
            'bank_name' => $this->bank_name,
            'created_at' => $this->created_at,
        ];
    }
}
