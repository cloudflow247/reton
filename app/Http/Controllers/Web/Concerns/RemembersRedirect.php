<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Concerns;

use Illuminate\Http\Request;

trait RemembersRedirect
{
    protected function rememberRedirect(Request $request): void
    {
        $redirect = $request->string('redirect')->toString();

        if ($redirect === '' || ! str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            return;
        }

        $request->session()->put('url.intended', $redirect);
    }
}
