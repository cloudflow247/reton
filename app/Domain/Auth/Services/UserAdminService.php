<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Settings\Services\PlatformSettingsService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserAdminService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly WalletService $wallets,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, password: string, is_admin?: bool, status?: string}  $data
     */
    public function create(User $admin, array $data, ?string $ip = null): User
    {
        $email = strtolower(trim($data['email']));

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['A user with this email already exists.'],
            ]);
        }

        return DB::transaction(function () use ($admin, $data, $email, $ip): User {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => $email,
                'phone' => isset($data['phone']) ? trim((string) $data['phone']) : null,
                'country' => 'NG',
                'password' => $data['password'],
                'status' => $data['status'] ?? 'active',
            ]);

            $user->forceFill([
                'is_admin' => (bool) ($data['is_admin'] ?? false),
                'email_verified_at' => now(),
            ])->save();

            $this->wallets->open($user, 'NGN');

            $this->settings->audit($admin, 'user.created', 'users', [
                'target_id' => $user->getKey(),
                'email' => $email,
            ], $ip);

            return $user->fresh();
        });
    }

    /**
     * @param  array{name?: string, phone?: string|null, status?: string, is_admin?: bool}  $data
     */
    public function update(User $admin, User $target, array $data, ?string $ip = null): User
    {
        if ($admin->is($target) && array_key_exists('is_admin', $data) && ! (bool) $data['is_admin']) {
            throw ValidationException::withMessages([
                'is_admin' => ['You cannot remove your own administrator access.'],
            ]);
        }

        if ($admin->is($target) && ($data['status'] ?? $target->status) !== 'active') {
            throw ValidationException::withMessages([
                'status' => ['You cannot suspend or freeze your own account.'],
            ]);
        }

        if (array_key_exists('is_admin', $data) && ! (bool) $data['is_admin'] && $target->is_admin) {
            $this->assertNotLastAdmin($target);
        }

        $changes = [];

        if (isset($data['name'])) {
            $changes['name'] = trim($data['name']);
        }

        if (array_key_exists('phone', $data)) {
            $changes['phone'] = $data['phone'] !== null && $data['phone'] !== ''
                ? trim((string) $data['phone'])
                : null;
        }

        if (isset($data['status'])) {
            $changes['status'] = $data['status'];
        }

        if (array_key_exists('is_admin', $data)) {
            $changes['is_admin'] = (bool) $data['is_admin'];
        }

        if ($changes === []) {
            return $target;
        }

        $target->update($changes);

        $this->settings->audit($admin, 'user.updated', 'users', [
            'target_id' => $target->getKey(),
            'changes' => array_keys($changes),
        ], $ip);

        return $target->fresh();
    }

    public function delete(User $admin, User $target, ?string $ip = null): void
    {
        if ($admin->is($target)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own account from the admin panel.'],
            ]);
        }

        if ($target->is_admin) {
            $this->assertNotLastAdmin($target);
        }

        DB::transaction(function () use ($admin, $target, $ip): void {
            $email = $target->email;

            $target->delete();

            $this->settings->audit($admin, 'user.deleted', 'users', [
                'target_id' => $target->getKey(),
                'email' => $email,
            ], $ip);
        });
    }

    private function assertNotLastAdmin(User $target): void
    {
        $adminCount = User::query()->where('is_admin', true)->count();

        if ($target->is_admin && $adminCount <= 1) {
            throw ValidationException::withMessages([
                'is_admin' => ['Reton must keep at least one platform administrator.'],
            ]);
        }
    }
}
