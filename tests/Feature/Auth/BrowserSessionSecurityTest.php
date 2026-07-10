<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('does not issue a remember cookie by default', function () {
    $user = readyUser(['password' => Hash::make('password')]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);

    $recaller = Auth::guard('web')->getRecallerName();
    $hasRecaller = collect($response->headers->getCookies())
        ->contains(fn ($cookie) => $cookie->getName() === $recaller);

    expect($hasRecaller)->toBeFalse();
});

it('issues a remember cookie when stay signed in is checked', function () {
    $user = readyUser(['password' => Hash::make('password')]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);

    $recaller = Auth::guard('web')->getRecallerName();
    $hasRecaller = collect($response->headers->getCookies())
        ->contains(fn ($cookie) => $cookie->getName() === $recaller);

    expect($hasRecaller)->toBeTrue();
});

it('invalidates other browser sessions after a new login', function () {
    [$user] = readyUserWithWallet(['password' => 'password']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->get('/dashboard')->assertOk();
    $staleHash = session('password_hash_web');
    $passwordAfterFirstLogin = $user->fresh()->getAuthPassword();
    expect($staleHash)->not->toBeNull();

    // New browser: sign out locally then sign in again (rehashes for device logout).
    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    expect($user->fresh()->getAuthPassword())->not->toBe($passwordAfterFirstLogin);

    $this->get('/dashboard')->assertOk();
    expect(session('password_hash_web'))->not->toBe($staleHash);

    // Stale password hash from the first session must force logout.
    $this->withSession(['password_hash_web' => $staleHash])
        ->get('/dashboard')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('uses hardened session defaults for browser cookies', function () {
    expect(config('session.encrypt'))->toBeTrue()
        ->and((int) config('session.lifetime'))->toBeLessThanOrEqual(60)
        ->and(filter_var(config('session.expire_on_close'), FILTER_VALIDATE_BOOLEAN))->toBeTrue()
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');
});

it('invalidates the session completely on logout', function () {
    $user = readyUser(['password' => Hash::make('password')]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post('/logout')->assertRedirect('/');

    $this->assertGuest();
    $this->get('/dashboard')->assertRedirect(route('login'));
});
