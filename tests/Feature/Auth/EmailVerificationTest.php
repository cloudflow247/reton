<?php

declare(strict_types=1);

use App\Mail\VerifyEmailMail;
use App\Models\User;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function verifiedWebUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->forceFill(['transaction_pin' => Hash::make('1234')])->save();

    app(WalletService::class)->open($user, 'NGN');

    return $user->fresh();
}

it('redirects new registrations to verify email', function () {
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
    expect($user->email_verified_at)->toBeNull();

    Mail::assertSent(VerifyEmailMail::class, fn (VerifyEmailMail $mail) => $mail->hasTo('ada@retonpay.com'));
});

it('blocks unverified users from the dashboard', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
});

it('verifies email via signed link and sends user to onboarding', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->getKey(),
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)
        ->assertRedirect(route('onboarding'));

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('allows resending the verification email', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post('/email/verification-notification')
        ->assertRedirect()
        ->assertSessionHas('status', 'verification-link-sent');

    Mail::assertSent(VerifyEmailMail::class);
});

it('redirects verified users without a pin to onboarding after login', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'transaction_pin' => null,
    ]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('onboarding'));
});

it('renders the verify email page for unverified users', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/email/verify')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/VerifyEmail'));
});

it('renders the onboarding wizard for verified users without a pin', function () {
    $user = User::factory()->create(['transaction_pin' => null]);

    $this->actingAs($user)->get('/onboarding')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Onboarding')->where('initialStep', 0));
});

it('allows a fully set up user to reach the dashboard', function () {
    $user = verifiedWebUser();

    expect($user->hasTransactionPin())->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();

    $this->actingAs($user)->get('/dashboard')->assertOk();
});
