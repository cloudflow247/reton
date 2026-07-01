<?php

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
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render every exception thrown on the API surface as Reton's standard
        // error envelope, so clients never receive an HTML error page.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                // Web/Inertia surface: turn expected domain outcomes (fraud
                // block, PIN lock, insufficient funds…) into a friendly flash
                // redirect instead of a 500/HTML error page. ValidationException
                // and auth redirects keep Laravel's default web handling.
                if ($e instanceof RenderableApiException) {
                    return back()->with('error', $e->getMessage());
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
