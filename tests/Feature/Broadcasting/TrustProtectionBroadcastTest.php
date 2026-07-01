<?php

declare(strict_types=1);

use App\Events\Trust\TrustProtectionChanged;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => '12345',
        'broadcasting.connections.reverb.options' => [
            'host' => 'localhost',
            'port' => 8081,
            'scheme' => 'http',
            'useTLS' => false,
        ],
    ]);

    // channels.php runs at boot against the null driver in phpunit.xml — register on reverb for this suite.
    require base_path('routes/channels.php');
});

it('authorizes a user for their private broadcast channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $socketId = '1.1';

    $this->actingAs($user, 'web')
        ->postJson('/broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => 'private-users.'.$user->id,
        ])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    $this->actingAs($other, 'web')
        ->postJson('/broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => 'private-users.'.$user->id,
        ])
        ->assertForbidden();
});

it('broadcasts trust protection changes on the user private channel', function () {
    Event::fake([TrustProtectionChanged::class]);

    $event = new TrustProtectionChanged('user-1', 'callback.initiated', ['status' => 'pending']);

    expect($event->broadcastAs())->toBe('trust.protection.changed')
        ->and($event->broadcastOn()[0]->name)->toBe('private-users.user-1');
});
