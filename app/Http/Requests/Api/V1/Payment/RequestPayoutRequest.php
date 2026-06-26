<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payment;

use Illuminate\Foundation\Http\FormRequest;

class RequestPayoutRequest extends FormRequest
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
            'wallet_id' => ['required', 'uuid', 'exists:wallets,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'bank_code' => ['required', 'string', 'max:16'],
            'account_number' => ['required', 'string', 'regex:/^\d{10}$/'],
            'account_name' => ['required', 'string', 'max:120'],
            'pin' => ['required', 'string'],
        ];
    }
}
