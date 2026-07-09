<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        $user = $request->user();

        if ($user === null
            || ! ($user instanceof MustVerifyEmail)
            || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            abort(403, 'Your email address is not verified.');
        }

        return redirect()->route($redirectToRoute ?: 'verification.notice');
    }
}
