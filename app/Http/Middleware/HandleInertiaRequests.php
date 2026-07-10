<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Resources\Api\V1\UserResource;
use App\Http\Resources\Api\V1\WalletResource;
use App\Models\User;
use App\Support\Admin\AdminPath;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the asset version so Inertia can force a full reload when the
     * frontend build changes.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props shared with every Inertia response. The authenticated user and their
     * wallets replace the old /auth/me fetch; flash messages replace toast state.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            // Lazy: controllers may credit wallets (static VA poll) before the
            // Inertia response is built — eager share() would freeze balance at ₦0.
            'auth' => fn (): array => [
                'user' => $request->user()
                    ? (new UserResource($request->user()))->resolve()
                    : null,
                'wallets' => $request->user()
                    ? WalletResource::collection($request->user()->wallets()->get())->resolve()
                    : [],
            ],
            // Demo helper for reviewers — only present when explicitly enabled
            // (RETON_DEMO_MODE), so production never exposes credentials.
            'demo' => config('reton.demo.enabled') ? [
                'password' => (string) config('reton.demo.password'),
                'pin' => (string) config('reton.demo.pin'),
                'accounts' => array_map(
                    static fn (array $a): array => ['name' => $a['name'], 'email' => $a['email']],
                    (array) config('reton.demo.accounts', []),
                ),
            ] : null,
            // Only expose the secret admin URL to administrators.
            'adminPath' => ($user !== null && $user->is_admin) ? AdminPath::url() : null,
            // Product surfaces gated behind Coming Soon until live providers are ready.
            'features' => [
                'withdraw' => (bool) config('reton.features.withdraw', false),
                'bills' => (bool) config('reton.features.bills', false),
                'cards' => (bool) config('reton.features.cards', false),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // Result payloads for screens that show an outcome after a POST
                // (the funded virtual account; the completed transfer receipt).
                'deposit' => fn () => $request->session()->get('deposit'),
                'transfer' => fn () => $request->session()->get('transfer'),
                'bill' => fn () => $request->session()->get('bill'),
                'payout' => fn () => $request->session()->get('payout'),
                'support_ticket' => fn () => $request->session()->get('support_ticket'),
            ],
        ];
    }
}
