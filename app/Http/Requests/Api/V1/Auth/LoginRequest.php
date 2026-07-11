<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Concerns\RejectsBotHoneypot;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use RejectsBotHoneypot;

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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
            'website' => ['nullable', 'string', 'max:0'],
            'company_url' => ['nullable', 'string', 'max:0'],
            'fax_number' => ['nullable', 'string', 'max:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->rejectIfHoneypotFilled();

        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }
}
