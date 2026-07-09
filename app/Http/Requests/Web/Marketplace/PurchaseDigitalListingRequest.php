<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseDigitalListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'min:4', 'max:6', 'regex:/^\d+$/'],
            'buyer_accepts_description' => ['sometimes', 'boolean'],
            'shipping_line1' => ['sometimes', 'string', 'max:120'],
            'shipping_line2' => ['nullable', 'string', 'max:120'],
            'shipping_city' => ['sometimes', 'string', 'max:80'],
            'shipping_state' => ['sometimes', 'string', 'max:80'],
            'shipping_phone' => ['sometimes', 'string', 'max:20'],
        ];
    }
}
