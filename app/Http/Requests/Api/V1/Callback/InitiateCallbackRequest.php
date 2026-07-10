<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Callback;

use Illuminate\Foundation\Http\FormRequest;

class InitiateCallbackRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:8', 'max:280'],
            'pin' => ['required', 'string'],
        ];
    }
}
