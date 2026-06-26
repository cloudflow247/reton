<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Payments\Models\PaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentRequest
 */
class PaymentRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'title' => $this->title,
            'description' => $this->description,
            'payment_link_url' => $this->payment_link_url,
            'payer_name' => $this->payer_name,
            'payer_email' => $this->payer_email,
            'expires_at' => $this->expires_at,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
