<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function actingUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

it('sets a transaction pin for a user without one', function () {
    $user = actingUser();

    $this->actingAs($user)->postJson('/api/v1/auth/pin', [
        'pin' => '1234',
        'pin_confirmation' => '1234',
    ])->assertOk()->assertJsonPath('success', true);

    expect($user->fresh()->hasTransactionPin())->toBeTrue()
        ->and(Hash::check('1234', $user->fresh()->transaction_pin))->toBeTrue();
});

it('rejects a pin that is not confirmed', function () {
    $user = actingUser();

    $this->actingAs($user)->postJson('/api/v1/auth/pin', [
        'pin' => '1234',
        'pin_confirmation' => '9999',
    ])->assertStatus(422)->assertJsonPath('code', 'validation_error');
});

it('rejects a non-numeric or wrong-length pin', function () {
    $user = actingUser();

    $this->actingAs($user)->postJson('/api/v1/auth/pin', [
        'pin' => '12ab',
        'pin_confirmation' => '12ab',
    ])->assertStatus(422);
});

it('verifies a correct pin', function () {
    $user = actingUser(['transaction_pin' => Hash::make('4321')]);

    $this->actingAs($user)->postJson('/api/v1/auth/pin/verify', [
        'pin' => '4321',
    ])->assertOk()->assertJsonPath('success', true);
});

it('rejects an incorrect pin and counts the attempt', function () {
    $user = actingUser(['transaction_pin' => Hash::make('4321')]);

    $this->actingAs($user)->postJson('/api/v1/auth/pin/verify', [
        'pin' => '0000',
    ])->assertStatus(422)->assertJsonPath('code', 'invalid_pin');

    expect($user->fresh()->pin_attempts)->toBe(1);
});

it('locks the pin after too many failed attempts', function () {
    config(['reton.pin.max_attempts' => 3]);
    $user = actingUser(['transaction_pin' => Hash::make('4321')]);

    foreach (range(1, 3) as $ignored) {
        $this->actingAs($user)->postJson('/api/v1/auth/pin/verify', ['pin' => '0000']);
    }

    // Even the correct pin is refused while locked.
    $this->actingAs($user)->postJson('/api/v1/auth/pin/verify', ['pin' => '4321'])
        ->assertStatus(423)
        ->assertJsonPath('code', 'pin_locked');

    expect($user->fresh()->pin_locked_until)->not->toBeNull();
});

it('requires the current pin when changing an existing pin', function () {
    $user = actingUser(['transaction_pin' => Hash::make('1111')]);

    // Without the current pin, the change is refused.
    $this->actingAs($user)->postJson('/api/v1/auth/pin', [
        'pin' => '2222',
        'pin_confirmation' => '2222',
    ])->assertStatus(422)->assertJsonPath('code', 'invalid_pin');

    // With the correct current pin, the change succeeds.
    $this->actingAs($user)->postJson('/api/v1/auth/pin', [
        'current_pin' => '1111',
        'pin' => '2222',
        'pin_confirmation' => '2222',
    ])->assertOk();

    expect(Hash::check('2222', $user->fresh()->transaction_pin))->toBeTrue();
});
