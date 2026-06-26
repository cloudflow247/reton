<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Callback;

use Illuminate\Foundation\Http\FormRequest;

class AcceptCallbackRequest extends FormRequest
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
