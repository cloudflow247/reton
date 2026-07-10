<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Data\DeviceContext;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly DeviceRegistrar $devices,
    ) {}

    /**
     * Register a new customer and provision their primary (NGN) wallet.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, ?DeviceContext $device = null): User
    {
        return DB::transaction(function () use ($data, $device): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'country' => $data['country'] ?? 'NG',
                'password' => $data['password'],
                'status' => 'active',
            ]);

            $this->wallets->open($user, 'NGN');
            $this->devices->remember($user, $device);

            $user->sendEmailVerificationNotification();

            return $user;
        });
    }

    public function login(string $email, string $password, ?DeviceContext $device = null): User
    {
        $email = strtolower(trim($email));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            throw InvalidCredentialsException::make();
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $this->devices->remember($user, $device);

        return $user;
    }
}
