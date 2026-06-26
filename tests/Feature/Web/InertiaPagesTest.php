<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * A user with their primary NGN wallet (and a transaction PIN), mirroring the
 * helpers used across the API feature tests.
 */
function webUser(int $fundMinor = 0, string $pin = '1234'): array
{
    $user = User::factory()->create(['transaction_pin' => Hash::make($pin)]);
    $wallet = app(WalletService::class)->open($user, 'NGN');

    if ($fundMinor > 0) {
        app(WalletService::class)->fund($wallet, Money::of($fundMinor, 'NGN'));
        $wallet->refresh();
    }

    return [$user, $wallet];
}

/*
|--------------------------------------------------------------------------
| Public + guest routing
|--------------------------------------------------------------------------
*/
it('renders the public home page for guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Public/Home'));
});

it('redirects guests away from the dashboard to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('sends authenticated users from / to the dashboard', function () {
    [$user] = webUser();

    $this->actingAs($user)->get('/')->assertRedirect('/dashboard');
});

/*
|--------------------------------------------------------------------------
| Session auth
|--------------------------------------------------------------------------
*/
it('registers a user, opens a wallet, and signs them in', function () {
    $this->post('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@reton.ng',
        'phone' => '+2348012345678',
        'password' => 'Sup3r-Secret!',
        'password_confirmation' => 'Sup3r-Secret!',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticated();

    $user = User::where('email', 'ada@reton.ng')->firstOrFail();
    expect($user->wallets()->count())->toBe(1);
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('rejects a login with the wrong password', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs the user out', function () {
    [$user] = webUser();

    $this->actingAs($user)->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| Authenticated screens render the right Inertia component
|--------------------------------------------------------------------------
*/
it('renders each authenticated screen', function (string $path, string $component) {
    [$user] = webUser();

    $this->actingAs($user)->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'dashboard' => ['/dashboard', 'Dashboard'],
    'send' => ['/send', 'Send'],
    'add money' => ['/add-money', 'AddMoney'],
    'bills' => ['/bills', 'Bills'],
    'receive' => ['/receive', 'Receive'],
    'activity' => ['/activity', 'Activity'],
    'profile' => ['/profile', 'Profile'],
    'pin' => ['/pin', 'SetPin'],
    'protection' => ['/protection', 'Protection'],
]);

it('shares the authenticated user and wallets with every page', function () {
    [$user, $wallet] = webUser();

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.email', $user->email)
            ->where('auth.wallets.0.id', $wallet->id)
            ->has('activity'));
});

it('passes transfers, callbacks and recoveries to the protection page', function () {
    [$user] = webUser();

    $this->actingAs($user)->get('/protection')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Protection')
            ->has('transfers')
            ->has('callbacks')
            ->has('recoveries')
            ->has('walletId'));
});

/*
|--------------------------------------------------------------------------
| Mutations: transfer, deposit, pin, lookup
|--------------------------------------------------------------------------
*/
it('completes a transfer and flashes a receipt', function () {
    [$sender, $from] = webUser(1_000_00);
    [$recipient, $to] = webUser();
    $recipient->forceFill(['name' => 'Bola Tinubu'])->save();

    $this->actingAs($sender)->post('/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 250_00,
        'type' => 'normal',
        'pin' => '1234',
    ])->assertSessionHas('transfer');

    expect($from->fresh()->balance)->toBe(75000)
        ->and($to->fresh()->balance)->toBe(25000);
});

it('rejects a transfer with the wrong pin and moves no money', function () {
    [$sender, $from] = webUser(1_000_00);
    [, $to] = webUser();

    $this->actingAs($sender)->post('/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 100_00,
        'type' => 'normal',
        'pin' => '9999',
    ])->assertSessionHasErrors('pin');

    expect($from->fresh()->balance)->toBe(100000);
});

it('initiates a deposit and flashes the virtual account', function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway);
    [$user, $wallet] = webUser();

    $this->actingAs($user)->post('/deposits', [
        'wallet_id' => $wallet->id,
        'amount' => 500_00,
    ])->assertSessionHas('deposit');
});

it('sets a transaction pin', function () {
    $user = User::factory()->create(['transaction_pin' => null]);
    app(WalletService::class)->open($user, 'NGN');

    $this->actingAs($user)->post('/pin', [
        'pin' => '4321',
        'pin_confirmation' => '4321',
    ])->assertSessionHas('success');

    expect($user->fresh()->hasTransactionPin())->toBeTrue();
});

it('resolves an account number to its holder via the web lookup', function () {
    [$me] = webUser();
    $recipient = User::factory()->create(['name' => 'Grace Hopper']);
    $recipientWallet = app(WalletService::class)->open($recipient, 'NGN');

    $this->actingAs($me)->getJson("/lookup?account_number={$recipientWallet->account_number}")
        ->assertOk()
        ->assertJsonPath('account_name', 'Grace Hopper')
        ->assertJsonPath('wallet_id', $recipientWallet->id);
});
