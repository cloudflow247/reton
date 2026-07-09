<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Models\Payout;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway);
});

/**
 * @return array{0: User, 1: Wallet}
 */
function withdrawWebUser(int $fund = 1_000_00, string $name = 'Ada Lovelace'): array
{
    $user = User::factory()->create([
        'name' => $name,
        'transaction_pin' => Hash::make('1234'),
    ]);
    $wallet = app(WalletService::class)->open($user, 'NGN');
    app(WalletService::class)->fund($wallet, Money::of($fund, 'NGN'));

    return [$user, $wallet->refresh()];
}

it('renders the withdraw page with banks', function () {
    [$user] = withdrawWebUser();

    $this->actingAs($user)->get('/withdraw')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Withdraw')
            ->has('banks')
            ->where('accountNameHint', 'ADA LOVELACE'));
});

it('initiates a withdrawal when the account name matches the profile', function () {
    [$user, $wallet] = withdrawWebUser();

    $this->actingAs($user)->post('/withdraw', [
        'wallet_id' => $wallet->id,
        'amount' => 400_00,
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'ADA LOVELACE',
        'pin' => '1234',
    ])->assertRedirect('/withdraw')
        ->assertSessionHas('payout');

    expect($wallet->fresh()->balance)->toBe(60000)
        ->and(Payout::where('user_id', $user->id)->exists())->toBeTrue();
});

it('rejects a withdrawal when the account name does not match', function () {
    [$user, $wallet] = withdrawWebUser();

    $this->actingAs($user)->post('/withdraw', [
        'wallet_id' => $wallet->id,
        'amount' => 400_00,
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'JOHN DOE',
        'pin' => '1234',
    ])->assertSessionHasErrors('account_name');

    expect($wallet->fresh()->balance)->toBe(100000)
        ->and(Payout::count())->toBe(0);
});

it('rejects a withdrawal with the wrong pin', function () {
    [$user, $wallet] = withdrawWebUser();

    $this->actingAs($user)->post('/withdraw', [
        'wallet_id' => $wallet->id,
        'amount' => 400_00,
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'ADA LOVELACE',
        'pin' => '0000',
    ])->assertSessionHasErrors('pin');

    expect($wallet->fresh()->balance)->toBe(100000);
});
