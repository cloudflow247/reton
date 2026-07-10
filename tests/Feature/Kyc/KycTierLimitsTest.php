<?php

declare(strict_types=1);

use App\Domain\Kyc\Enums\KycTier;
use App\Domain\Kyc\Services\KycService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('caps tier 2 bvn-verified wallet balance at one hundred thousand naira', function () {
    $user = readyUser();
    $kyc = app(KycService::class)->forUser($user);
    $kyc->forceFill(['tier' => KycTier::Tier2])->save();

    $limits = app(KycService::class)->limitsFor($kyc->fresh());

    expect($limits['wallet_balance_max'])->toBe(100_000_00)
        ->and($limits['single_transaction_max'])->toBe(100_000_00)
        ->and($limits['daily_inflow_max'])->toBe(100_000_00);
});

it('keeps tier 1 below the bvn-verified balance cap', function () {
    expect((int) config('reton.kyc.tiers.1.wallet_balance_max'))
        ->toBeLessThan((int) config('reton.kyc.tiers.2.wallet_balance_max'))
        ->and((int) config('reton.kyc.tiers.2.wallet_balance_max'))->toBe(100_000_00);
});

it('exposes the trust score on the dashboard homepage', function () {
    $user = readyUser();

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('summary.trust_score')
            ->where('summary.trust_score', 100));
});
