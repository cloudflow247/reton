<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Admin\AdminPath;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidAdminPath implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! AdminPath::isValid($value)) {
            $fail('Use 3–48 lowercase letters, numbers, and hyphens — not a reserved app route.');
        }
    }
}
