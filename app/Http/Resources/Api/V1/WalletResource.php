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
        // Inline clamps - avoid calling Wallet helpers so a stale OPcache
        // Wallet class after deploy cannot 500 every authenticated Inertia page.
        $ledger = max(0, (int) $this->balance);
        $held = max(0, (int) $this->held_balance);

        return [
            'id' => $this->id,
            'account_number' => $this->account_number,
            'currency' => $this->currency,
            'balance' => $ledger,
            'held_balance' => $held,
            'available_balance' => max(0, $ledger - $held),
            'status' => $this->status,
        ];
    }
}
