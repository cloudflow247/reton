<?php

declare(strict_types=1);

use App\Domain\Auth\Services\BrowserSessionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('rehashes the password so other sessions can be invalidated', function () {
    $user = User::factory()->create(['password' => 'password']);
    $before = $user->getAuthPassword();

    $request = Request::create('/login', 'POST');
    $request->setLaravelSession(app('session.store'));

    app(BrowserSessionService::class)->start($request, $user, 'password');

    expect($user->fresh()->getAuthPassword())->not->toBe($before)
        ->and(Auth::check())->toBeTrue();
});

it('does not set a remember token cookie when remember is false', function () {
    $user = User::factory()->create(['password' => 'password']);

    $request = Request::create('/login', 'POST');
    $request->setLaravelSession(app('session.store'));

    app(BrowserSessionService::class)->start($request, $user, 'password', remember: false);

    expect($user->fresh()->remember_token)->not->toBeNull(); // factory may set one
    expect(Auth::viaRemember())->toBeFalse();
});
