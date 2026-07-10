<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Marketplace;

use App\Domain\Marketplace\Enums\DigitalDisputeCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RaiseDigitalDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'category' => ['required', 'string', Rule::enum(DigitalDisputeCategory::class)],
            'details' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
