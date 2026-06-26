<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Bill;

use App\Domain\Bills\Enums\BillCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayBillRequest extends FormRequest
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
            'category' => ['required', Rule::enum(BillCategory::class)],
            'biller_code' => ['required', 'string', 'max:64'],
            // The biller name and amount are authoritative from the RRR lookup,
            // so they are only supplied by the payer for amount-entered bills.
            'biller_name' => ['required_unless:category,rrr', 'nullable', 'string', 'max:120'],
            'customer_reference' => ['required', 'string', 'max:64'],
            'amount' => ['required_unless:category,rrr', 'nullable', 'integer', 'min:1'],
            'pin' => ['required', 'string'],
        ];
    }
}
