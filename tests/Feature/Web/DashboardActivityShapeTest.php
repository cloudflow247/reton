<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shares wallets as a plain list for inertia', function () {
    [$user] = readyUserWithWallet();

    $wallets = $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->original->getData()['page']['props']['auth']['wallets'];

    expect($wallets)->toBeArray()->and(array_is_list($wallets))->toBeTrue();
});
