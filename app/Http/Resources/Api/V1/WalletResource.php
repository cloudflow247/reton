<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Wallet\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wallet
 */
class WalletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_number' => $this->account_number,
            'currency' => $this->currency,
            'balance' => $this->ledgerMinor(),
            'held_balance' => $this->heldMinor(),
            'available_balance' => $this->availableMinor(),
            'status' => $this->status,
        ];
    }
}
