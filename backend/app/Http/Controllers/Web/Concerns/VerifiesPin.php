<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Concerns;

use App\Support\Exceptions\RenderableApiException;
use App\Models\User;
use App\Domain\Auth\Services\PinService;
use Illuminate\Validation\ValidationException;

/**
 * Web controllers verify the transaction PIN inline so a wrong/locked PIN comes
 * back as a form error on the `pin` field (Inertia) rather than a 422 envelope.
 */
trait VerifiesPin
{
    protected function verifyPin(PinService $pins, User $user, string $pin): void
    {
        try {
            $pins->verify($user, $pin);
        } catch (RenderableApiException $e) {
            throw ValidationException::withMessages(['pin' => $e->getMessage()]);
        }
    }
}
