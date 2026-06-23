<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Exceptions\InvalidPinException;
use App\Domain\Auth\Exceptions\PinLockedException;
use App\Domain\Auth\Exceptions\PinNotSetException;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Manages a user's transaction PIN: the second factor required to authorise
 * money movements, independent of the login password.
 */
class PinService
{
    /**
     * Set or change the transaction PIN.
     *
     * Changing an existing PIN requires the current PIN; setting one for the
     * first time does not.
     */
    public function set(User $user, string $newPin, ?string $currentPin = null): void
    {
        if ($user->hasTransactionPin()
            && ($currentPin === null || ! Hash::check($currentPin, (string) $user->transaction_pin))) {
            throw InvalidPinException::make();
        }

        $user->forceFill([
            'transaction_pin' => Hash::make($newPin),
            'pin_attempts' => 0,
            'pin_locked_until' => null,
        ])->save();
    }

    /**
     * Verify a PIN, enforcing lockout after repeated failures.
     */
    public function verify(User $user, string $pin): void
    {
        if (! $user->hasTransactionPin()) {
            throw PinNotSetException::make();
        }

        if ($this->isLocked($user)) {
            throw PinLockedException::make();
        }

        if (! Hash::check($pin, (string) $user->transaction_pin)) {
            $this->registerFailure($user);

            throw InvalidPinException::make();
        }

        $user->forceFill([
            'pin_attempts' => 0,
            'pin_locked_until' => null,
        ])->save();
    }

    private function isLocked(User $user): bool
    {
        $lockedUntil = $user->pin_locked_until;

        return $lockedUntil !== null && Carbon::parse($lockedUntil)->isFuture();
    }

    private function registerFailure(User $user): void
    {
        $attempts = $user->pin_attempts + 1;
        $maxAttempts = (int) config('reton.pin.max_attempts', 5);

        $changes = ['pin_attempts' => $attempts];

        if ($attempts >= $maxAttempts) {
            $changes['pin_locked_until'] = now()->addMinutes((int) config('reton.pin.lockout_minutes', 15));
        }

        $user->forceFill($changes)->save();
    }
}
