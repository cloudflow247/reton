<?php

declare(strict_types=1);

use App\Domain\Payments\Contracts\PayoutGateway;
use App\Domain\Payments\Models\Payout;
use App\Domain\Payments\Paystack\Gateways\FakePaystackPayoutGateway;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'reton.features.withdraw' => true,
        'reton.payouts.provider' => 'paystack',
        'services.paystack.driver' => 'fake',
    ]);
    $this->app->instance(PayoutGateway::class, new FakePaystackPayoutGateway);
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
            ->where('accountNameHint', 'ADA LOVELACE')
            ->where('payoutsAvailable', true)
            ->missing('payoutProvider')
            ->has('recentPayouts', 0));
});

it('renders recent payouts on the withdraw page', function () {
    [$user, $wallet] = withdrawWebUser();

    Payout::create([
        'reference' => 'PO-RECENT001',
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'provider' => 'paystack',
        'status' => 'pending',
        'amount' => 25_000,
        'currency' => 'NGN',
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'ADA LOVELACE',
    ]);

    $this->actingAs($user)->get('/withdraw')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Withdraw')
            ->has('recentPayouts', 1)
            ->where('recentPayouts.0.reference', 'PO-RECENT001')
            ->where('recentPayouts.0.status', 'pending'));
});

it('still renders withdraw when a payout has an unexpected status value', function () {
    [$user, $wallet] = withdrawWebUser();

    $payout = Payout::create([
        'reference' => 'PO-BADSTATUS',
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'provider' => 'paystack',
        'status' => 'pending',
        'amount' => 10_000,
        'currency' => 'NGN',
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'ADA LOVELACE',
    ]);

    \Illuminate\Support\Facades\DB::table('payouts')
        ->where('id', $payout->id)
        ->update(['status' => 'bogus']);

    $this->actingAs($user)->get('/withdraw')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Withdraw')
            ->has('recentPayouts', 1)
            ->where('recentPayouts.0.status', 'bogus'));
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
        ->and(Payout::where('user_id', $user->id)->where('provider', 'paystack')->exists())->toBeTrue();
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

it('does not debit the wallet when outbound payouts are unavailable', function () {
    $gateway = Mockery::mock(PayoutGateway::class);
    $gateway->shouldReceive('supportsOutboundTransfers')->andReturn(false);
    $this->app->instance(PayoutGateway::class, $gateway);

    [$user, $wallet] = withdrawWebUser();

    $this->actingAs($user)->post('/withdraw', [
        'wallet_id' => $wallet->id,
        'amount' => 400_00,
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'ADA LOVELACE',
        'pin' => '1234',
    ])->assertRedirect()
        ->assertSessionHas('error');

    expect($wallet->fresh()->balance)->toBe(100000)
        ->and(Payout::count())->toBe(0);
});

it('shows coming soon when withdraw is disabled', function () {
    config(['reton.features.withdraw' => false]);
    [$user] = withdrawWebUser();

    $this->actingAs($user)->get('/withdraw')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ComingSoon')
            ->where('feature', 'withdraw')
            ->where('title', 'Withdraw to bank')
            ->where('description', fn (string $text) => ! str_contains(strtolower($text), 'alatpay')
                && ! str_contains(strtolower($text), 'paystack')));
});

it('rejects withdraw posts when the feature is disabled', function () {
    config(['reton.features.withdraw' => false]);
    [$user, $wallet] = withdrawWebUser();

    $this->actingAs($user)->post('/withdraw', [
        'wallet_id' => $wallet->id,
        'amount' => 400_00,
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'ADA LOVELACE',
        'pin' => '1234',
    ])->assertRedirect()
        ->assertSessionHas('error');

    expect($wallet->fresh()->balance)->toBe(100000)
        ->and(Payout::count())->toBe(0);
});
