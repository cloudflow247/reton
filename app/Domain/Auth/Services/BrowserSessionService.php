<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Establishes hardened browser sessions for the web guard.
 *
 * - Rotates the session id (fixation protection)
 * - Invalidates other device sessions via password-hash binding
 * - Purges leftover database session rows for the user
 * - Remember-me is opt-in only
 */
class BrowserSessionService
{
    /**
     * Start a session after a password-authenticated login.
     */
    public function start(Request $request, User $user, string $password, bool $remember = false): void
    {
        Auth::guard('web')->login($user, remember: $remember);
        Auth::logoutOtherDevices($password);

        $request->session()->regenerate();
        $this->forgetOtherDatabaseSessions($user, $request->session()->getId());
    }

    /**
     * Start a session when the password was already rotated (register / reset).
     */
    public function startFresh(Request $request, User $user, bool $remember = false): void
    {
        Auth::guard('web')->login($user, remember: $remember);
        $request->session()->regenerate();
        $this->forgetOtherDatabaseSessions($user, $request->session()->getId());
    }

    private function forgetOtherDatabaseSessions(User $user, string $currentSessionId): void
    {
        if (! Schema::hasTable('sessions') || config('session.driver') !== 'database') {
            return;
        }

        DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}
