<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    /** @var list<string> */
    private const EXEMPT = [
        'onboarding',
        'pin',
        'pin.update',
        'logout',
        'add-money',
        'deposits.store',
        'deposits.pay',
        'deposits.simulate-pay',
        'add-money.return',
        'verification.notice',
        'verification.verify',
        'verification.send',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->is_admin) {
            return $next($request);
        }

        if ($user !== null && ! $user->hasTransactionPin()) {
            foreach (self::EXEMPT as $name) {
                if ($request->routeIs($name)) {
                    return $next($request);
                }
            }

            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
