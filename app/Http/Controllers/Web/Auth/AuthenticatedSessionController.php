<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Domain\Auth\Data\DeviceContext;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Auth\Services\AuthService;
use App\Http\Controllers\Web\Concerns\RemembersRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    use RemembersRedirect;

    public function __construct(private readonly AuthService $auth) {}

    public function create(Request $request): Response
    {
        $this->rememberRedirect($request);

        return Inertia::render('Auth/Login', [
            'redirect' => $request->string('redirect')->toString() ?: null,
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $user = $this->auth->login(
                $request->string('email')->toString(),
                $request->string('password')->toString(),
                DeviceContext::fromRequest($request),
            );
        } catch (InvalidCredentialsException $e) {
            throw ValidationException::withMessages([
                'email' => $e->getMessage(),
            ]);
        }

        // Establish the session for the web guard and rotate the session id.
        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
