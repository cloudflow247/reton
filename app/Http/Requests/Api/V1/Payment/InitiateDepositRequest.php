<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Payment;

use App\Domain\Payments\Enums\DepositMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InitiateDepositRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:100'],
            'method' => ['nullable', Rule::in(['bank_transfer', 'alatpay_checkout', 'alatpay_card'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $raw = $this->input('method', DepositMethod::BankTransfer->value);
            $method = DepositMethod::tryFrom(is_string($raw) ? $raw : '') ?? DepositMethod::BankTransfer;

            if (! $method->isEnabled()) {
                $validator->errors()->add(
                    'method',
                    match ($method) {
                        DepositMethod::AlatpayCheckout => 'Checkout funding is coming soon. Use your permanent deposit account.',
                        DepositMethod::AlatpayCard => 'Card funding is coming soon. Use your permanent deposit account.',
                        DepositMethod::BankTransfer => 'One-time transfer is coming soon. Use your permanent deposit account.',
                    },
                );
            }
        });
    }
}
