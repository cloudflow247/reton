<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Auth\Services\AuthService;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Provisions ready-to-use demo accounts surfaced on the sign-in screen when
 * RETON_DEMO_MODE is enabled. Idempotent: existing accounts are skipped.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // The house chart of accounts must exist before any wallet is funded.
        $this->call(SystemAccountsSeeder::class);

        $password = (string) config('reton.demo.password', 'demo1234');
        $pin = (string) config('reton.demo.pin', '1234');

        foreach ((array) config('reton.demo.accounts', []) as $account) {
            if (User::where('email', $account['email'])->exists()) {
                continue;
            }

            $user = app(AuthService::class)->register([
                'name' => $account['name'],
                'email' => $account['email'],
                'phone' => $account['phone'],
                'password' => $password,
            ]);

            $user->forceFill([
                'transaction_pin' => Hash::make($pin),
                'email_verified_at' => now(),
            ])->save();

            $fund = (int) ($account['fund'] ?? 0);
            if ($fund > 0) {
                /** @var Wallet $wallet */
                $wallet = $user->wallets()->firstOrFail();
                app(WalletService::class)->fund($wallet, Money::of($fund, 'NGN'));
            }
        }

        $this->call(DigitalMarketplaceDemoSeeder::class);
    }
}
