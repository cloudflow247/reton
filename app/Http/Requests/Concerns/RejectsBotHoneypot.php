<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\ValidationException;

trait RejectsBotHoneypot
{
    /**
     * Silent bot trap — filled honeypot fields fail with a generic auth error
     * so scrapers learn nothing about the trap field name.
     */
    protected function rejectIfHoneypotFilled(): void
    {
        $traps = ['website', 'company_url', 'fax_number'];

        foreach ($traps as $field) {
            if (filled($this->input($field))) {
                throw ValidationException::withMessages([
                    'email' => ['Unable to process this request. Please try again.'],
                ]);
            }
        }
    }
}
