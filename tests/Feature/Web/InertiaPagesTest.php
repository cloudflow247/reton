<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\DepositMethod;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Services\AlatpayDepositService;
use App\Domain\Wallet\Services\WalletService;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * A user with their primary NGN wallet (and a transaction PIN), mirroring the
 * helpers used across the API feature tests.
 */
function webUser(int $fundMinor = 0, string $pin = '1234'): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->forceFill(['transaction_pin' => Hash::make($pin)])->save();
    $user->refresh();
    ensureVerifiedBvn($user);
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

it('renders public marketing pages for guests', function (string $path, string $component) {
    $this->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'business' => ['/business', 'Public/Business'],
    'about' => ['/about', 'Public/About'],
    'faq' => ['/faq', 'Public/Faq'],
    'contact' => ['/contact', 'Public/Contact'],
    'security' => ['/security', 'Public/Security'],
    'how it works' => ['/how-it-works', 'Public/HowItWorks'],
]);

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
it('registers a user, opens a wallet, and sends them to verify email', function () {
    Mail::fake();

    $this->post('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@retonpay.com',
        'phone' => '+2348012345678',
        'password' => 'Sup3r-Secret!',
        'password_confirmation' => 'Sup3r-Secret!',
    ])->assertRedirect(route('verification.notice'));

    $this->assertAuthenticated();

    $user = User::where('email', 'ada@retonpay.com')->firstOrFail();
    expect($user->wallets()->count())->toBe(1)
        ->and($user->email_verified_at)->toBeNull();

    Mail::assertSent(VerifyEmailMail::class);
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $user->forceFill(['transaction_pin' => Hash::make('1234')])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('rejects a login with the wrong password', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('password');

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
    'support' => ['/support', 'Support'],
]);

it('shares the authenticated user and wallets with every page', function () {
    [$user, $wallet] = webUser();

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.email', $user->email)
            ->where('auth.wallets.0.id', $wallet->id)
            ->has('summary')
            ->has('activity')
            ->has('kycTier'));
});

it('passes transfers, callbacks and recoveries to the protection page', function () {
    [$user] = webUser();

    $this->actingAs($user)->get('/protection')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Protection')
            ->has('transfers')
            ->has('callbacks')
            ->has('recoveries')
            ->has('digitalOrders')
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
        'method' => 'bank_transfer',
    ])->assertRedirect(route('add-money', ['reference' => Deposit::latest()->first()->reference]));
});

it('redirects to the pay route for alatpay checkout', function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway);
    [$user, $wallet] = webUser();

    $this->actingAs($user)->post('/deposits', [
        'wallet_id' => $wallet->id,
        'amount' => 500_00,
        'method' => 'alatpay_checkout',
    ])->assertRedirect(route('deposits.pay', Deposit::latest()->first()));
});

it('shows the local demo checkout when alatpay driver is fake', function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway);
    [$user, $wallet] = webUser();

    $deposit = app(AlatpayDepositService::class)->initiate(
        $user,
        $wallet,
        Money::of(500_00, 'NGN'),
        DepositMethod::AlatpayCheckout,
    );

    $this->actingAs($user)->get(route('deposits.pay', $deposit))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Deposits/AlatpayDemoCheckout')
            ->where('deposit.reference', $deposit->reference)
            ->where('cardOnly', false));
});

it('simulates a successful alatpay payment in demo mode', function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway);
    [$user, $wallet] = webUser();

    $deposit = app(AlatpayDepositService::class)->initiate(
        $user,
        $wallet,
        Money::of(500_00, 'NGN'),
        DepositMethod::AlatpayCard,
    );

    $this->actingAs($user)->post(route('deposits.simulate-pay', $deposit))
        ->assertRedirect(route('add-money', ['reference' => $deposit->reference]));

    expect($deposit->fresh()->status->value)->toBe('completed')
        ->and($wallet->fresh()->balance)->toBe(50000);
});

it('restores a pending bank transfer after reload via reference', function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway);
    [$user, $wallet] = webUser();

    $deposit = app(AlatpayDepositService::class)->initiate(
        $user,
        $wallet,
        Money::of(500_00, 'NGN'),
    );

    $this->actingAs($user)->get('/add-money?reference='.$deposit->reference)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AddMoney')
            ->where('pendingDeposit.reference', $deposit->reference)
            ->where('pendingDeposit.virtual_account.account_number', $deposit->virtual_account['account_number']));
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
