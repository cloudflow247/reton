<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Bills\Models\BillPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BillPayment
 */
class BillPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'provider' => $this->provider,
            'status' => $this->status->value,
            'category' => $this->category->value,
            'category_label' => $this->category->displayName(),
            'biller_code' => $this->biller_code,
            'biller_name' => $this->biller_name,
            'customer_reference' => $this->customer_reference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'failure_reason' => $this->failure_reason,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
        ];
    }
}
