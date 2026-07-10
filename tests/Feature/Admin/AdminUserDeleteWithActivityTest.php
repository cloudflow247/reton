<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('removes a user with an empty wallet via admin http', function () {
    $admin = readyUser(['is_admin' => true]);
    [$target] = readyUserWithWallet();

    $this->actingAs($admin)->delete("/admin/users/{$target->getKey()}")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(User::query()->find($target->getKey()))->toBeNull();
    expect(User::withTrashed()->find($target->getKey()))->not->toBeNull();
});

it('removes a user via service using secure soft delete', function () {
    $admin = readyUser(['is_admin' => true]);
    [$target] = readyUserWithWallet();

    app(\App\Domain\Auth\Services\UserAdminService::class)->delete($admin, $target);

    expect(User::query()->find($target->getKey()))->toBeNull();
    expect(User::withTrashed()->find($target->getKey()))->not->toBeNull();
});
