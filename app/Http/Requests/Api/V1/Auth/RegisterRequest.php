<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Concerns\RejectsBotHoneypot;
use App\Support\Auth\CountryDialCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
        $namePart = ['string', 'max:60', 'regex:/^[\p{L}\p{M}\s\'\-\.]+$/u'];

        return [
            'first_name' => ['required_without:name', 'nullable', ...$namePart],
            'middle_name' => ['nullable', ...$namePart],
            'last_name' => ['required_without:name', 'nullable', ...$namePart],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'country_iso' => ['nullable', 'string', 'size:2', Rule::in(CountryDialCodes::isoCodes())],
            'country_code' => ['nullable', 'string', 'max:6', Rule::in(CountryDialCodes::dialCodes())],
            'phone_national' => ['nullable', 'string', 'max:15', 'regex:/^[0-9\s\-]+$/'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone', 'regex:/^\+[1-9]\d{7,14}$/'],
            'country' => ['sometimes', 'string', 'size:2', Rule::in(CountryDialCodes::isoCodes())],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'website' => ['nullable', 'string', 'max:0'],
            'company_url' => ['nullable', 'string', 'max:0'],
            'fax_number' => ['nullable', 'string', 'max:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required_without' => 'Enter your first name.',
            'last_name.required_without' => 'Enter your last name.',
            'first_name.regex' => 'First name may only contain letters, spaces, hyphens, and apostrophes.',
            'middle_name.regex' => 'Middle name may only contain letters, spaces, hyphens, and apostrophes.',
            'last_name.regex' => 'Last name may only contain letters, spaces, hyphens, and apostrophes.',
            'phone.regex' => 'Enter a valid mobile number with country code.',
            'phone_national.regex' => 'Enter your mobile number using digits only.',
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

        $first = trim((string) $this->input('first_name', ''));
        $middle = trim((string) $this->input('middle_name', ''));
        $last = trim((string) $this->input('last_name', ''));

        if ($first !== '' || $last !== '') {
            $parts = array_values(array_filter([$first, $middle, $last], static fn (string $part): bool => $part !== ''));
            $this->merge([
                'first_name' => $first !== '' ? $first : null,
                'middle_name' => $middle !== '' ? $middle : null,
                'last_name' => $last !== '' ? $last : null,
                'name' => implode(' ', $parts),
            ]);
        } elseif ($this->filled('name')) {
            $this->merge([
                'name' => trim(preg_replace('/\s+/', ' ', (string) $this->input('name')) ?? ''),
            ]);
        }

        $dial = preg_replace('/\D+/', '', (string) $this->input('country_code', '')) ?? '';
        $national = (string) $this->input('phone_national', '');
        $iso = strtoupper(trim((string) $this->input('country_iso', $this->input('country', ''))));

        if ($dial !== '' && trim($national) !== '') {
            $this->merge([
                'country_code' => $dial,
                'phone' => CountryDialCodes::toE164($dial, $national),
                'country' => $iso !== '' ? $iso : 'NG',
            ]);
        } elseif ($this->filled('phone')) {
            $phone = preg_replace('/[^\d+]/', '', (string) $this->input('phone')) ?? '';
            if (! str_starts_with($phone, '+') && $phone !== '') {
                $phone = '+'.$phone;
            }
            $this->merge([
                'phone' => $phone,
                'country' => $iso !== '' ? $iso : ((string) $this->input('country', 'NG') ?: 'NG'),
            ]);
        }
    }
}
