<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class DeliverDigitalOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'attest_matches_listing' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'attest_matches_listing.accepted' => 'Confirm that your delivery matches the listing before continuing.',
        ];
    }
}
