<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recovery;

use Illuminate\Foundation\Http\FormRequest;

class ReturnRecoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pin' => ['required', 'string'],
        ];
    }
}
