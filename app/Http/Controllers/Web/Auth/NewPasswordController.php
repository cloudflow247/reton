<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Domain\Auth\Services\BrowserSessionService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\RedirectsAfterAuth;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    use RedirectsAfterAuth;

    public function __construct(private readonly BrowserSessionService $sessions) {}

    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->string('email')->toString(),
            'token' => $token,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (filled($request->input('website')) || filled($request->input('company_url')) || filled($request->input('fax_number'))) {
            throw ValidationException::withMessages([
                'email' => ['Unable to process this request. Please try again.'],
            ]);
        }

        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'website' => ['nullable', 'string', 'max:0'],
            'company_url' => ['nullable', 'string', 'max:0'],
            'fax_number' => ['nullable', 'string', 'max:0'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user !== null) {
            // Password already rotated - other browser sessions fail AuthenticateSession.
            $this->sessions->startFresh($request, $user);

            return redirect()->intended($this->redirectAfterAuth($user));
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
