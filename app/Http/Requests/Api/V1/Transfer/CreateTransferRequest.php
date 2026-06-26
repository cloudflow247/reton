<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Transfer;

use App\Domain\Transfers\Enums\TransferType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CreateTransferRequest extends FormRequest
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
            'from_wallet_id' => ['required', 'uuid', 'exists:wallets,id'],
            'to_wallet_id' => ['required', 'uuid', 'exists:wallets,id', Rule::notIn([$this->input('from_wallet_id')])],
            'amount' => ['required', 'integer', 'min:1'],
            'type' => ['required', new Enum(TransferType::class)],
            'pin' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:140'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_wallet_id.not_in' => 'You cannot transfer to the same wallet.',
        ];
    }
}
