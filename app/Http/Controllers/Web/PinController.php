<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Auth\Services\PinService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SetPinRequest;
use App\Models\User;
use App\Support\Exceptions\RenderableApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class PinController extends Controller
{
    public function __construct(private readonly PinService $pins) {}

    public function update(SetPinRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $hadPin = $user->hasTransactionPin();

        try {
            $this->pins->set(
                $user,
                $request->string('pin')->toString(),
                $request->filled('current_pin') ? $request->string('current_pin')->toString() : null,
            );
        } catch (RenderableApiException $e) {
            // A wrong current PIN surfaces on the current_pin field.
            throw ValidationException::withMessages(['current_pin' => $e->getMessage()]);
        }

        $user->refresh();

        if (! $hadPin && $user->hasTransactionPin()) {
            if ($request->boolean('from_onboarding')) {
                return redirect()
                    ->route('onboarding', ['step' => 2])
                    ->with('success', 'PIN saved — wallet secured.');
            }

            return redirect()->route('dashboard')->with('success', 'PIN set — your wallet is ready.');
        }

        return back()->with('success', 'Transaction PIN updated.');
    }
}
