<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class StoreDigitalListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'price' => ['required', 'integer', 'min:100'],
            'delivery_payload' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }
}
