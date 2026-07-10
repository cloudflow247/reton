<?php

use App\Http\Middleware\ApplyPlatformSecurityHeaders;
use App\Http\Middleware\EnsureAdminPath;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Exceptions\RenderableApiException;
use App\Support\Http\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Inertia shares auth + flash on every web response and handles
        // asset-version reloads. Only the web group is server-rendered.
        $middleware->web(append: [
            ApplyPlatformSecurityHeaders::class,
            HandleInertiaRequests::class,
        ]);

        // Bind sessions to the password hash so password changes / login on
        // another device invalidate leftover browser sessions.
        $middleware->authenticateSessions();

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'admin.path' => EnsureAdminPath::class,
            'onboarding' => EnsureOnboardingComplete::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render every exception thrown on the API surface as Reton's standard
        // error envelope, so clients never receive an HTML error page.
        $exceptions->render(function (Throwable $e, Request $request) {
            // Inertia XHR must never receive the API JSON envelope — that body has
            // no X-Inertia header, so the client shows a generic "500 | SERVER ERROR".
            if ($request->header('X-Inertia') || (! $request->is('api/*') && ! $request->expectsJson())) {
                if ($e instanceof RenderableApiException) {
                    return back()->with('error', $e->getMessage());
                }

                // Temporarily surface unexpected withdraw failures so production can
                // be diagnosed without Cloud log access. Remove once fixed.
                if (
                    $request->routeIs('withdraw', 'withdraw.store')
                    && ! $e instanceof ValidationException
                    && ! $e instanceof AuthenticationException
                    && ! $e instanceof AuthorizationException
                    && ! $e instanceof HttpExceptionInterface
                ) {
                    report($e);

                    return redirect()
                        ->route('dashboard')
                        ->with('error', 'Withdraw failed: '.$e->getMessage());
                }

                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::validationError($e->errors(), $e->getMessage()),

                $e instanceof AuthenticationException => ApiResponse::error('Unauthenticated.', 'unauthenticated', 401),

                $e instanceof AuthorizationException,
                $e instanceof AccessDeniedHttpException => ApiResponse::error('This action is unauthorized.', 'forbidden', 403),

                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error('Resource not found.', 'not_found', 404),

                $e instanceof RenderableApiException => ApiResponse::error($e->getMessage(), $e->apiCode(), $e->apiStatus()),

                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    $e->getMessage() ?: 'Request failed.',
                    'http_error',
                    $e->getStatusCode(),
                ),

                default => ApiResponse::error(
                    config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
                    'server_error',
                    500,
                ),
            };
        });
    })->create();
