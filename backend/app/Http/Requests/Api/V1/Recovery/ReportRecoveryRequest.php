<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recovery;

use Illuminate\Foundation\Http\FormRequest;

class ReportRecoveryRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:280'],
            'pin' => ['required', 'string'],
        ];
    }
}
