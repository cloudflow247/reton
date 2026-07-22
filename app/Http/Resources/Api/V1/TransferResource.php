<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Transfers\Models\Transfer;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transfer
 */
class TransferResource extends JsonResource
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
            'note' => $this->note,
            'sender_wallet_id' => $this->sender_wallet_id,
            'receiver_wallet_id' => $this->receiver_wallet_id,
            'sender' => $this->partyFromWallet(
                $this->relationLoaded('senderWallet') ? $this->senderWallet : null,
            ),
            'receiver' => $this->partyFromWallet(
                $this->relationLoaded('receiverWallet') ? $this->receiverWallet : null,
            ),
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'metadata' => $this->metadata,
            'hold' => $this->when(
                $this->relationLoaded('hold') && $this->hold !== null,
                fn () => (new HoldResource($this->hold))->resolve(),
            ),
        ];
    }

    /**
     * @return array{name: string|null, reton_id: string|null}|null
     */
    private function partyFromWallet(?Wallet $wallet): ?array
    {
        if ($wallet === null) {
            return null;
        }

        $owner = $wallet->relationLoaded('owner') ? $wallet->owner : null;

        return [
            'name' => $owner instanceof User ? (string) $owner->name : null,
            'reton_id' => $wallet->account_number !== null ? (string) $wallet->account_number : null,
        ];
    }
}
