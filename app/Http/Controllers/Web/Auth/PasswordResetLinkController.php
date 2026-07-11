<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
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
            'email' => ['required', 'string', 'email'],
            'website' => ['nullable', 'string', 'max:0'],
            'company_url' => ['nullable', 'string', 'max:0'],
            'fax_number' => ['nullable', 'string', 'max:0'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email'),
        );

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return back()->with('status', __($status));
    }
}
