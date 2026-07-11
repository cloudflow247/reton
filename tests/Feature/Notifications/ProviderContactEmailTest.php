<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Banking\ProviderContactEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds a reton-owned provider contact email for alatpay', function () {
    $user = User::factory()->create();

    $email = ProviderContactEmail::forUser($user);

    expect($email)->toEndWith('@va.retonpay.com')
        ->and($email)->toStartWith('u')
        ->and(ProviderContactEmail::recoveryCandidates($user))->toContain(strtolower($email));
});

it('includes the customer email among recovery candidates for legacy accounts', function () {
    $user = User::factory()->create(['email' => 'customer@example.com']);

    expect(ProviderContactEmail::recoveryCandidates($user))
        ->toContain('customer@example.com')
        ->toContain(strtolower(ProviderContactEmail::forUser($user)));
});
