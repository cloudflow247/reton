<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Domain\Auth\Data\DeviceContext;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Auth\Services\BrowserSessionService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\RedirectsAfterAuth;
use App\Http\Controllers\Web\Concerns\RemembersRedirect;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
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

        return Inertia::render('Auth/Register', [
            'redirect' => $request->string('redirect')->toString() ?: null,
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = $this->auth->register(
            $request->validated(),
            DeviceContext::fromRequest($request),
        );

        $this->sessions->startFresh($request, $user);

        return redirect()->intended($this->redirectAfterAuth($user));
    }
}
