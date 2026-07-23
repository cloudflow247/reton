<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Callback\Models\Callback;
use App\Domain\Marketplace\Models\DigitalOrder;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

            $fresh = $user->fresh();

            if ($fresh === null) {
                throw new \RuntimeException('User missing after create.');
            }

            return $fresh;
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

        $fresh = $target->fresh();

        if ($fresh === null) {
            throw new \RuntimeException('User missing after update.');
        }

        return $fresh;
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

        $this->assertSafeToRemove($target);

        try {
            DB::transaction(function () use ($admin, $target, $ip): void {
                $originalEmail = $target->email;

                $this->revokeAccess($target);

                $target->forceFill([
                    'name' => 'Removed user',
                    'email' => $this->anonymizedEmail($target),
                    'phone' => null,
                    'status' => 'frozen',
                    'password' => Hash::make(Str::random(64)),
                    'transaction_pin' => null,
                    'remember_token' => null,
                    'pin_attempts' => 0,
                    'pin_locked_until' => null,
                ])->save();

                $target->delete();

                $this->settings->audit($admin, 'user.deleted', 'users', [
                    'target_id' => $target->getKey(),
                    'email' => $originalEmail,
                    'mode' => 'soft_delete',
                ], $ip);
            });
        } catch (QueryException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'user' => ['This account could not be removed because it still has linked financial records. Suspend the user instead, or contact engineering.'],
            ]);
        }
    }

    /**
     * @return array{user: array<string, mixed>, wallets: list<array<string, mixed>>, kyc: array<string, mixed>|null, recent_transfers: list<array<string, mixed>>}
     */
    public function deskProfile(User $target): array
    {
        $target->loadMissing(['kyc', 'wallets']);

        $wallets = $target->wallets->map(fn (Wallet $wallet): array => [
            'id' => $wallet->id,
            'currency' => $wallet->currency,
            'balance' => (int) $wallet->balance,
            'held_balance' => (int) $wallet->held_balance,
            'available' => $wallet->availableMinor(),
            'status' => $wallet->status ?? 'active',
        ])->values()->all();

        $kyc = $target->kyc;

        $walletIds = $target->wallets->pluck('id');
        $transfers = array_values(Transfer::query()
            ->when($walletIds->isNotEmpty(), function ($query) use ($walletIds): void {
                $query->where(function ($inner) use ($walletIds): void {
                    $inner->whereIn('sender_wallet_id', $walletIds)
                        ->orWhereIn('receiver_wallet_id', $walletIds);
                });
            }, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn ($transfer): array => [
                'id' => $transfer->id,
                'reference' => $transfer->reference,
                'type' => $transfer->type->value,
                'status' => $transfer->status->value,
                'amount' => (int) $transfer->amount,
                'currency' => $transfer->currency,
                'created_at' => $transfer->created_at?->toIso8601String(),
            ])
            ->all());

        return [
            'user' => [
                'id' => $target->getKey(),
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'status' => $target->status,
                'is_admin' => $target->is_admin,
                'email_verified' => $target->email_verified_at !== null,
                'has_pin' => filled($target->transaction_pin),
                'last_login_at' => $target->last_login_at?->toIso8601String(),
                'created_at' => $target->created_at?->toIso8601String(),
            ],
            'wallets' => array_values($wallets),
            'kyc' => $kyc === null ? null : [
                'tier' => $kyc->tier->value,
                'bvn_last4' => $kyc->bvn_last4,
                'nin_last4' => $kyc->nin_last4,
                'bvn_verified_at' => $kyc->bvn_verified_at?->toIso8601String(),
                'nin_verified_at' => $kyc->nin_verified_at?->toIso8601String(),
                'city' => $kyc->city,
                'state' => $kyc->state,
            ],
            'recent_transfers' => $transfers,
        ];
    }

    private function assertSafeToRemove(User $target): void
    {
        $walletIds = $target->wallets()->pluck('id');

        if ($walletIds->isNotEmpty()) {
            $aggregate = Wallet::query()
                ->whereIn('id', $walletIds)
                ->selectRaw('coalesce(sum(balance), 0) as total_balance, coalesce(sum(held_balance), 0) as total_held')
                ->first();

            $totalBalance = (int) ($aggregate->total_balance ?? 0);
            $totalHeld = (int) ($aggregate->total_held ?? 0);

            if ($totalBalance > 0 || $totalHeld > 0) {
                throw ValidationException::withMessages([
                    'user' => ['This user still has wallet funds. Ask them to withdraw or transfer the balance before removing the account.'],
                ]);
            }
        }

        $openCallbacks = Callback::query()
            ->whereIn('status', ['pending', 'escalated'])
            ->where(function ($query) use ($target, $walletIds): void {
                $query->where('initiated_by', $target->getKey());

                if ($walletIds->isNotEmpty()) {
                    $query->orWhereHas('transfer', function ($transfer) use ($walletIds): void {
                        $transfer->whereIn('sender_wallet_id', $walletIds)
                            ->orWhereIn('receiver_wallet_id', $walletIds);
                    });
                }
            })
            ->exists();

        if ($openCallbacks) {
            throw ValidationException::withMessages([
                'user' => ['This user has open callback protection cases. Resolve them before removing the account.'],
            ]);
        }

        $openRecoveries = Recovery::query()
            ->where(function ($query) use ($target, $walletIds): void {
                $query->where('reported_by', $target->getKey());

                if ($walletIds->isNotEmpty()) {
                    $query->orWhereIn('sender_wallet_id', $walletIds)
                        ->orWhereIn('receiver_wallet_id', $walletIds);
                }
            })
            ->whereIn('status', ['held', 'escalated'])
            ->exists();

        if ($openRecoveries) {
            throw ValidationException::withMessages([
                'user' => ['This user has active recovery cases. Close them before removing the account.'],
            ]);
        }

        $openOrders = DigitalOrder::query()
            ->where(function ($query) use ($target): void {
                $query->where('buyer_id', $target->getKey())
                    ->orWhere('seller_id', $target->getKey());
            })
            ->whereIn('status', ['paid_held', 'awaiting_verification', 'shipped', 'delivered', 'disputed'])
            ->exists();

        if ($openOrders) {
            throw ValidationException::withMessages([
                'user' => ['This user has open marketplace orders. Settle or cancel them before removing the account.'],
            ]);
        }
    }

    private function revokeAccess(User $target): void
    {
        $target->tokens()->delete();

        DB::table('sessions')->where('user_id', $target->getKey())->delete();
        DB::table('password_reset_tokens')->where('email', $target->email)->delete();
    }

    private function anonymizedEmail(User $target): string
    {
        $local = 'removed+'.$target->getKey();

        return substr($local, 0, 64).'@removed.retonpay.com';
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
