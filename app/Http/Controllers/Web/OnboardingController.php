<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $requestedStep = (int) $request->integer('step', 0);

        if ($user->hasTransactionPin()) {
            if ($requestedStep >= 2) {
                return Inertia::render('Onboarding', [
                    'initialStep' => 2,
                ]);
            }

            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding', [
            'initialStep' => max(0, min($requestedStep, 1)),
        ]);
    }
}
