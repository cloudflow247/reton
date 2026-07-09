<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Support;

use Illuminate\Foundation\Http\FormRequest;

class EscalateSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:3', 'max:120'],
            'note' => ['nullable', 'string', 'max:2000'],
            'transfer_reference' => ['nullable', 'string', 'max:64'],
        ];
    }
}
