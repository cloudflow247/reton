<?php

declare(strict_types=1);

use App\Domain\Auth\Models\Device;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+2348012345678',
        'password' => 'Sup3r-Secret!',
        'password_confirmation' => 'Sup3r-Secret!',
    ], $overrides);
}

it('registers a user, returns a token and provisions an NGN wallet', function () {
    $response = $this->postJson('/api/v1/auth/register', registerPayload());

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'ada@example.com')
        ->assertJsonStructure(['success', 'message', 'data' => ['user' => ['id', 'email'], 'token']]);

    $user = User::where('email', 'ada@example.com')->firstOrFail();

    expect($user->wallets()->where('currency', 'NGN')->exists())->toBeTrue()
        ->and(Wallet::where('currency', 'NGN')->count())->toBe(1);
});

it('rejects registration with invalid data using the validation envelope', function () {
    $response = $this->postJson('/api/v1/auth/register', registerPayload([
        'email' => 'not-an-email',
        'password_confirmation' => 'mismatch',
    ]));

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonStructure(['errors' => ['email', 'password']]);
});

it('rejects registration with a duplicate email', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/v1/auth/register', registerPayload())
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error');
});

it('captures the device when registration carries device headers', function () {
    $this->withHeaders([
        'X-Device-Fingerprint' => 'fp-abc-123',
        'X-Device-Name' => 'Pixel 9',
        'X-Device-Platform' => 'android',
    ])->postJson('/api/v1/auth/register', registerPayload())->assertCreated();

    $device = Device::where('fingerprint', 'fp-abc-123')->first();

    expect($device)->not->toBeNull()
        ->and($device->name)->toBe('Pixel 9')
        ->and($device->platform)->toBe('android');
});

it('logs in with valid credentials and issues a working token', function () {
    $user = User::factory()->create([
        'email' => 'grace@example.com',
        'password' => 'correct-horse',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'grace@example.com',
        'password' => 'correct-horse',
    ]);

    $response->assertOk()->assertJsonPath('success', true);
    $token = $response->json('data.token');

    expect($token)->not->toBeEmpty()
        ->and(PersonalAccessToken::findToken($token))->not->toBeNull()
        ->and($user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects login with the wrong password', function () {
    User::factory()->create([
        'email' => 'grace@example.com',
        'password' => 'correct-horse',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'grace@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');
});

it('returns the authenticated user and wallets from /me', function () {
    $this->postJson('/api/v1/auth/register', registerPayload())->assertCreated();
    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'Sup3r-Secret!',
    ])->json('data.token');

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.email', 'ada@example.com')
        ->assertJsonPath('data.wallets.0.currency', 'NGN');
});

it('rejects /me without a token', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('logs out by revoking the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    expect($user->fresh()->tokens()->count())->toBe(0);
});
