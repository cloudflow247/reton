<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Auth\Services\UserAdminService;
use App\Domain\Payments\Data\ProviderContactRebindResult;
use App\Domain\Payments\Services\ProviderContactRebindService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminStoreUserRequest;
use App\Http\Requests\Admin\AdminUpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminUsersController extends Controller
{
    public function __construct(
        private readonly UserAdminService $users,
        private readonly ProviderContactRebindService $providerContact,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.strtolower($search).'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->whereRaw('lower(name) like ?', [$term])
                        ->orWhereRaw('lower(email) like ?', [$term])
                        ->orWhereRaw('lower(phone) like ?', [$term]);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => $this->serializeUser($user));

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'filters' => ['q' => $search],
            'statusOptions' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'suspended', 'label' => 'Suspended'],
                ['value' => 'frozen', 'label' => 'Frozen'],
            ],
        ]);
    }

    public function store(AdminStoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $this->users->create(
            $request->user(),
            $request->validated(),
            $request->ip(),
        );

        return back()->with('success', 'User created successfully.');
    }

    public function update(AdminUpdateUserRequest $request, string $adminPrefix, User $user): RedirectResponse
    {
        unset($adminPrefix);

        $this->authorize('update', $user);

        $this->users->update(
            $request->user(),
            $user,
            $request->validated(),
            $request->ip(),
        );

        return back()->with('success', 'User updated.');
    }

    public function destroy(Request $request, string $adminPrefix, User $user): RedirectResponse
    {
        unset($adminPrefix);

        $this->authorize('delete', $user);

        $this->users->delete($request->user(), $user, $request->ip());

        return back()->with('success', 'User removed and access revoked.');
    }

    public function rebindProviderEmail(Request $request, string $adminPrefix, User $user): RedirectResponse
    {
        unset($adminPrefix);

        $this->authorize('update', $user);

        try {
            $result = $this->providerContact->rebindForUser($user, dryRun: false, actorIp: $request->ip());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        if ($result->status === ProviderContactRebindResult::STATUS_NEEDS_SUPPORT
            || $result->status === ProviderContactRebindResult::STATUS_MISSING_ACCOUNT) {
            return back()->with('error', $result->message);
        }

        return back()->with('success', $result->message);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'is_admin' => $user->is_admin,
            'email_verified' => $user->email_verified_at !== null,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
