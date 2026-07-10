<?php

declare(strict_types=1);

use App\Domain\Settings\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the admin users page for administrators', function () {
    $admin = readyUser(['is_admin' => true]);
    $customer = readyUser(['name' => 'Ada Customer']);

    $this->actingAs($admin)->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users.data', 2)
            ->where('users.total', 2)
            ->where('filters.q', ''));
});

it('searches users by name email or phone', function () {
    $admin = readyUser(['is_admin' => true]);
    readyUser(['name' => 'Ada Customer', 'email' => 'ada@example.com']);
    readyUser(['name' => 'Bob Vendor', 'email' => 'bob@example.com', 'phone' => '+2348012345678']);

    $this->actingAs($admin)->get('/admin/users?q=ada')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.total', 1)
            ->where('users.data.0.email', 'ada@example.com'));
});

it('creates a user with wallet and audit log', function () {
    $admin = readyUser(['is_admin' => true]);

    $this->actingAs($admin)->post('/admin/users', [
        'name' => 'New User',
        'email' => 'new@retonpay.com',
        'phone' => '+2348099999999',
        'password' => 'SecurePass123!',
        'status' => 'active',
        'is_admin' => false,
    ])->assertRedirect()
        ->assertSessionHas('success');

    $created = User::query()->where('email', 'new@retonpay.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->email_verified_at)->not->toBeNull()
        ->and($created->wallets()->exists())->toBeTrue();

    expect(AdminAuditLog::query()->where('action', 'user.created')->exists())->toBeTrue();
});

it('updates a user and records audit log', function () {
    $admin = readyUser(['is_admin' => true]);
    $target = readyUser(['name' => 'Before Name', 'status' => 'active']);

    $this->actingAs($admin)->put("/admin/users/{$target->getKey()}", [
        'name' => 'After Name',
        'phone' => '+2348011111111',
        'status' => 'suspended',
        'is_admin' => false,
    ])->assertRedirect()
        ->assertSessionHas('success');

    $target->refresh();
    expect($target->name)->toBe('After Name')
        ->and($target->status)->toBe('suspended');

    expect(AdminAuditLog::query()->where('action', 'user.updated')->exists())->toBeTrue();
});

it('deletes a user and records audit log', function () {
    $admin = readyUser(['is_admin' => true]);
    $target = readyUser(['email' => 'remove@example.com']);

    $this->actingAs($admin)->delete("/admin/users/{$target->getKey()}")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(User::query()->find($target->getKey()))->toBeNull();
    expect(AdminAuditLog::query()->where('action', 'user.deleted')->exists())->toBeTrue();
});

it('forbids non-admins from user management', function () {
    $user = readyUser(['is_admin' => false]);
    $target = readyUser();

    $this->actingAs($user)->get('/admin/users')->assertForbidden();
    $this->actingAs($user)->post('/admin/users', [
        'name' => 'Hack',
        'email' => 'hack@example.com',
        'password' => 'SecurePass123!',
        'status' => 'active',
        'is_admin' => true,
    ])->assertForbidden();
    $this->actingAs($user)->put("/admin/users/{$target->getKey()}", [
        'name' => 'Hack',
        'status' => 'active',
        'is_admin' => true,
    ])->assertForbidden();
    $this->actingAs($user)->delete("/admin/users/{$target->getKey()}")->assertForbidden();
});

it('prevents administrators from deleting themselves', function () {
    $admin = readyUser(['is_admin' => true]);

    $this->actingAs($admin)->delete("/admin/users/{$admin->getKey()}")
        ->assertSessionHasErrors('user');
});

it('prevents administrators from removing their own admin access', function () {
    $admin = readyUser(['is_admin' => true]);

    $this->actingAs($admin)->put("/admin/users/{$admin->getKey()}", [
        'name' => $admin->name,
        'status' => 'active',
        'is_admin' => false,
    ])->assertSessionHasErrors('is_admin');
});

it('prevents removing the last platform administrator', function () {
    $admin = readyUser(['is_admin' => true]);

    $this->actingAs($admin)->put("/admin/users/{$admin->getKey()}", [
        'name' => $admin->name,
        'status' => 'suspended',
        'is_admin' => true,
    ])->assertSessionHasErrors('status');

    $this->actingAs($admin)->delete("/admin/users/{$admin->getKey()}")
        ->assertSessionHasErrors('user');
});

it('blocks login for suspended users', function () {
    $user = readyUser([
        'password' => bcrypt('password'),
        'status' => 'suspended',
    ]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});
