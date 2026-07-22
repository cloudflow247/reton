<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Payments\Models\PaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payer-facing view of a payment request - no requester PII.
 *
 * @mixin PaymentRequest
 */
class PublicPaymentRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'title' => $this->title,
            'description' => $this->description,
            'payment_link_url' => $this->payment_link_url,
        ];
    }
}
