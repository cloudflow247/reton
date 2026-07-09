<?php

declare(strict_types=1);

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

it('renders the forgot password page', function () {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
});

it('sends a branded password reset email', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('status');

    Mail::assertSent(ResetPasswordMail::class, fn (ResetPasswordMail $mail) => $mail->hasTo($user->email));
});

it('resets the password and signs the user in', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);
    $user->forceFill(['transaction_pin' => Hash::make('1234')])->save();

    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'New-Secret1',
        'password_confirmation' => 'New-Secret1',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect(Hash::check('New-Secret1', $user->fresh()->password))->toBeTrue();
});

it('renders the reset password form with token and email', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->get("/reset-password/{$token}?email=".urlencode($user->email))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/ResetPassword')
            ->where('email', $user->email)
            ->where('token', $token));
});
