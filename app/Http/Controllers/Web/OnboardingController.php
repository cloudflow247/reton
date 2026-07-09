<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTransactionPin()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding', [
            'initialStep' => $user->hasTransactionPin() ? 2 : 0,
        ]);
    }
}
