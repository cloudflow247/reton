<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Marketplace;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDigitalListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isPhysical = $this->string('item_type')->toString() === 'physical';

        return [
            'item_type' => ['required', Rule::in(['digital', 'physical'])],
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'price' => ['required', 'integer', 'min:100'],
            'delivery_payload' => [Rule::requiredIf(! $isPhysical), 'nullable', 'string', 'min:3', 'max:5000'],
            'condition' => [Rule::requiredIf($isPhysical), 'nullable', Rule::in(['new', 'like_new', 'good', 'fair'])],
            'weight_grams' => [Rule::requiredIf($isPhysical), 'nullable', 'integer', 'min:100', 'max:50000'],
            'spec_brand' => [Rule::requiredIf($isPhysical), 'nullable', 'string', 'max:80'],
            'spec_detail' => [Rule::requiredIf($isPhysical), 'nullable', 'string', 'max:120'],
            'handling_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $this->string('item_type')->toString() === 'physical'
                && ! (bool) config('reton.features.physical_listings', false)
            ) {
                $validator->errors()->add(
                    'item_type',
                    'Physical listings are coming soon. Publish a digital item for now.',
                );
            }
        });
    }
}
