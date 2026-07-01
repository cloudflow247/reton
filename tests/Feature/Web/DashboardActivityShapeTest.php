<?php

declare(strict_types=1);

use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shares wallets as a plain list for inertia', function () {
    $user = User::factory()->create();
    app(WalletService::class)->open($user, 'NGN');

    $wallets = $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->original->getData()['page']['props']['auth']['wallets'];

    expect($wallets)->toBeArray()->and(array_is_list($wallets))->toBeTrue();
});
