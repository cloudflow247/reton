<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Payments\Models\StaticAccount;
use App\Support\Banking\FundingAccountName;
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
        $profileName = '';
        if ($this->relationLoaded('user') && $this->user !== null) {
            $profileName = (string) $this->user->name;
        } elseif ($request->user() !== null && (string) $request->user()->getKey() === (string) $this->user_id) {
            $profileName = (string) $request->user()->name;
        }

        return [
            'id' => $this->id,
            'wallet_id' => $this->wallet_id,
            'wallet_type' => $this->wallet_type->value,
            'status' => $this->status->value,
            'account_number' => $this->account_number,
            'account_name' => FundingAccountName::display($this->account_name, $profileName),
            'bank_name' => $this->bank_name ?? 'ALAT by Wema',
            'needs_otp' => $this->status->value === 'pending_otp',
            'created_at' => $this->created_at,
        ];
    }
}
