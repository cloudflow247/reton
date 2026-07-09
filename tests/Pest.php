<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    // Inertia first-visit responses render the root Blade (which calls @vite);
    // stub Vite so feature tests don't require a built manifest.
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Domain\Kyc\Enums\KycTier;
use App\Domain\Kyc\Services\KycService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\Hash;

function ensureVerifiedBvn(User $user, ?string $bvn = null): User
{
    $kyc = app(KycService::class)->forUser($user);

    if ($kyc->decryptedBvn() !== null) {
        return $user->fresh();
    }

    if ($bvn === null) {
        $suffix = str_pad((string) (abs(crc32((string) $user->getKey())) % 1_000_000_000), 9, '0', STR_PAD_LEFT);
        $bvn = '22'.$suffix;
    }

    $kyc->storeBvn($bvn);
    $kyc->update([
        'tier' => KycTier::Tier2,
        'bvn_verified_at' => now(),
    ]);

    return $user->fresh();
}

function readyUser(array $attributes = [], string $pin = '1234'): User
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now(),
    ], $attributes));

    $user->forceFill(['transaction_pin' => Hash::make($pin)])->save();

    return $user->fresh();
}

function readyUserWithWallet(array $attributes = [], int $fundMinor = 0, string $pin = '1234'): array
{
    $user = readyUser($attributes, $pin);
    ensureVerifiedBvn($user);
    $wallet = app(WalletService::class)->open($user, 'NGN');

    if ($fundMinor > 0) {
        app(WalletService::class)->fund($wallet, Money::of($fundMinor, 'NGN'));
        $wallet->refresh();
    }

    return [$user, $wallet];
}
