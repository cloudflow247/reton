<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'status' => $this->status,
            'email_verified' => $this->email_verified_at !== null,
            'phone_verified' => $this->phone_verified_at !== null,
            'has_transaction_pin' => $this->hasTransactionPin(),
            'is_admin' => (bool) $this->is_admin,
            'notify_email' => (bool) $this->notify_email,
            'notify_sms' => (bool) $this->notify_sms,
            'created_at' => $this->created_at,
        ];
    }
}
