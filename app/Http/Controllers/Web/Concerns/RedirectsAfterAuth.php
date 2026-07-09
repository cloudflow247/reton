<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Concerns;

use App\Models\User;

trait RedirectsAfterAuth
{
    protected function redirectAfterAuth(User $user): string
    {
        if (! $user->hasVerifiedEmail()) {
            return route('verification.notice');
        }

        if (! $user->hasTransactionPin()) {
            return route('onboarding');
        }

        return route('dashboard');
    }
}
