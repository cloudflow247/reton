<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Banking\ProviderContactEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('routes alatpay contact mail to a plus-alias of the merchant ceo inbox', function () {
    config(['services.alatpay.merchant_email' => 'ceo@cloudfow.example']);

    $user = User::factory()->create();

    $email = ProviderContactEmail::forUser($user);

    expect($email)->toStartWith('ceo+va')
        ->and($email)->toEndWith('@cloudfow.example')
        ->and($email)->not->toBe('ceo@cloudfow.example')
        ->and(ProviderContactEmail::recoveryCandidates($user))->toContain(strtolower($email));
});

it('falls back to the reton provider domain when merchant email is missing', function () {
    config([
        'services.alatpay.merchant_email' => '',
        'services.alatpay.provider_contact_domain' => 'va.retonpay.com',
    ]);

    $user = User::factory()->create();

    expect(ProviderContactEmail::forUser($user))
        ->toStartWith('u')
        ->toEndWith('@va.retonpay.com');
});

it('includes the customer email among recovery candidates for legacy accounts', function () {
    config(['services.alatpay.merchant_email' => 'ceo@cloudfow.example']);

    $user = User::factory()->create(['email' => 'customer@example.com']);

    expect(ProviderContactEmail::recoveryCandidates($user))
        ->toContain('customer@example.com')
        ->toContain(strtolower(ProviderContactEmail::forUser($user)));
});
