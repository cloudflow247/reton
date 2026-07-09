<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Concerns;

use App\Models\User;
use App\Support\Admin\AdminPath;

trait RedirectsAfterAuth
{
    protected function redirectAfterAuth(User $user): string
    {
        if (! $user->hasVerifiedEmail()) {
            return route('verification.notice');
        }

        if ($user->is_admin) {
            return AdminPath::url();
        }

        if (! $user->hasTransactionPin()) {
            return route('onboarding');
        }

        return route('dashboard');
    }
}
