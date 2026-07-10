<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Domain\Auth\Data\DeviceContext;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Auth\Services\BrowserSessionService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\RedirectsAfterAuth;
use App\Http\Controllers\Web\Concerns\RemembersRedirect;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    use RedirectsAfterAuth;
    use RemembersRedirect;

    public function __construct(
        private readonly AuthService $auth,
        private readonly BrowserSessionService $sessions,
    ) {}

    public function create(Request $request): Response
    {
        $this->rememberRedirect($request);

        return Inertia::render('Auth/Login', [
            'redirect' => $request->string('redirect')->toString() ?: null,
            'email' => $request->old('email'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $password = $request->string('password')->toString();

        try {
            $user = $this->auth->login(
                $request->string('email')->toString(),
                $password,
                DeviceContext::fromRequest($request),
            );
        } catch (InvalidCredentialsException $e) {
            throw ValidationException::withMessages([
                'password' => $e->getMessage(),
            ]);
        }

        $this->sessions->start(
            $request,
            $user,
            $password,
            $request->boolean('remember'),
        );

        return redirect()->intended($this->redirectAfterAuth($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
