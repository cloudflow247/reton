<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults email alerts on and sms alerts off for new users', function () {
    $user = User::factory()->create();

    expect($user->notify_email)->toBeTrue()
        ->and($user->notify_sms)->toBeFalse();
});

it('updates notification preferences from the profile', function () {
    $user = readyUser();

    $this->actingAs($user)
        ->put('/profile/notifications', [
            'notify_email' => true,
            'notify_sms' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->fresh()->notify_sms)->toBeTrue()
        ->and($user->fresh()->notify_email)->toBeTrue();
});

it('rejects sms alerts when the user has no phone number', function () {
    $user = readyUser(['phone' => null]);

    $this->actingAs($user)
        ->from('/profile')
        ->put('/profile/notifications', [
            'notify_email' => true,
            'notify_sms' => true,
        ])
        ->assertRedirect('/profile')
        ->assertSessionHasErrors('notify_sms');

    expect($user->fresh()->notify_sms)->toBeFalse();
});

it('exposes the sms fee on the profile page', function () {
    $user = readyUser();

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Profile')
            ->where('smsAlertFeeMinor', 600));
});
