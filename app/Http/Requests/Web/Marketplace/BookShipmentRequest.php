<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class BookShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pickup_line1' => ['required', 'string', 'max:120'],
            'pickup_city' => ['required', 'string', 'max:80'],
            'pickup_state' => ['required', 'string', 'max:80'],
            'pickup_phone' => ['required', 'string', 'max:20'],
            'attest_matches_listing' => ['accepted'],
        ];
    }
}
