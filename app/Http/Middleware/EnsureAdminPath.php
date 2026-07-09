<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Admin\AdminPath;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin routes use a configurable first URL segment; reject unknown prefixes.
 */
class EnsureAdminPath
{
    public function handle(Request $request, Closure $next): Response
    {
        $prefix = (string) $request->segment(1);

        if (AdminPath::normalize($prefix) !== AdminPath::current()) {
            abort(404);
        }

        return $next($request);
    }
}
