<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ProvisionStaticAccountRequest extends FormRequest
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
            'wallet_type' => ['required', 'in:individual,collection'],
            'bvn' => ['required_if:wallet_type,individual', 'prohibited_unless:wallet_type,individual', 'digits:11'],
        ];
    }
}
