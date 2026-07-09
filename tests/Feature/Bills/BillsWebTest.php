<?php

declare(strict_types=1);

use App\Domain\Bills\Models\BillPayment;
use App\Domain\Bills\Remita\Contracts\BillProviderGateway;
use App\Domain\Bills\Remita\Gateways\FakeBillProvider;
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
        'reton.bills.provider' => 'interswitch',
        'services.interswitch.driver' => 'fake',
        'services.remita.driver' => 'fake',
    ]);
    $this->gateway = new FakeBillProvider;
    $this->app->instance(BillProviderGateway::class, $this->gateway);
    $this->app->instance(FakeBillProvider::class, $this->gateway);
});

/**
 * @return array{0: User, 1: Wallet}
 */
function billWebUser(int $fundMinor = 0, string $pin = '1234'): array
{
    $user = User::factory()->create(['transaction_pin' => Hash::make($pin)]);
    $wallet = app(WalletService::class)->open($user, 'NGN');

    if ($fundMinor > 0) {
        app(WalletService::class)->fund($wallet, Money::of($fundMinor, 'NGN'));
        $wallet->refresh();
    }

    return [$user, $wallet];
}

it('renders the bills page with categories and history', function () {
    [$user] = billWebUser();

    $this->actingAs($user)->get('/bills')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Bills')
            ->has('categories')
            ->has('bills'));
});

it('resolves an RRR over the lookup endpoint', function () {
    [$user] = billWebUser();
    $this->gateway->registerRrr('100000000003', 'JAMB', 5_000_00);

    $this->actingAs($user)->getJson('/bills/rrr?rrr=100000000003')
        ->assertOk()
        ->assertJsonPath('biller_name', 'JAMB')
        ->assertJsonPath('amount', 5_000_00);
});

it('rejects a malformed RRR', function () {
    [$user] = billWebUser();

    $this->actingAs($user)->getJson('/bills/rrr?rrr=123')
        ->assertStatus(422)
        ->assertJsonValidationErrors('rrr');
});

it('pays an airtime bill and flashes a receipt', function () {
    [$user, $wallet] = billWebUser(1_000_00);

    $this->actingAs($user)->post('/bills', [
        'wallet_id' => $wallet->id,
        'category' => 'airtime',
        'biller_code' => 'mtn',
        'biller_name' => 'MTN',
        'customer_reference' => '08030000000',
        'amount' => 200_00,
        'pin' => '1234',
    ])->assertSessionHas('bill');

    expect($wallet->fresh()->balance)->toBe(80000)
        ->and(BillPayment::where('user_id', $user->id)->count())->toBe(1);
});

it('pays a Remita RRR for its looked-up amount, ignoring any client amount', function () {
    [$user, $wallet] = billWebUser(100_000_00);
    $this->gateway->registerRrr('100000000004', 'FIRS', 25_000_00);

    $this->actingAs($user)->post('/bills', [
        'wallet_id' => $wallet->id,
        'category' => 'rrr',
        'biller_code' => 'remita',
        'customer_reference' => '100000000004',
        'pin' => '1234',
    ])->assertSessionHas('bill');

    expect($wallet->fresh()->balance)->toBe(100_000_00 - 25_000_00);
});

it('pays a betting wallet top-up via interswitch', function () {
    [$user, $wallet] = billWebUser(1_000_00);

    $this->actingAs($user)->post('/bills', [
        'wallet_id' => $wallet->id,
        'category' => 'betting',
        'biller_code' => 'sportybet',
        'biller_name' => 'SportyBet',
        'customer_reference' => 'SB123456',
        'amount' => 500_00,
        'pin' => '1234',
    ])->assertSessionHas('bill');

    expect($wallet->fresh()->balance)->toBe(50000);
});

it('rejects a bill with the wrong pin and moves no money', function () {
    [$user, $wallet] = billWebUser(1_000_00);

    $this->actingAs($user)->post('/bills', [
        'wallet_id' => $wallet->id,
        'category' => 'airtime',
        'biller_code' => 'mtn',
        'biller_name' => 'MTN',
        'customer_reference' => '08030000000',
        'amount' => 200_00,
        'pin' => '9999',
    ])->assertSessionHasErrors('pin');

    expect($wallet->fresh()->balance)->toBe(100000)
        ->and(BillPayment::count())->toBe(0);
});
