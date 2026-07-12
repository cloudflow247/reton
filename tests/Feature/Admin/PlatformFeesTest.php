<?php

declare(strict_types=1);

use App\Domain\Fees\Enums\FeeRail;
use App\Domain\Fees\Services\PlatformFeeService;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function feesAdmin(): \App\Models\User
{
    return readyUser(['is_admin' => true]);
}

it('renders platform fees settings and saves fee rails', function () {
    $admin = feesAdmin();

    $this->actingAs($admin)->get('/admin/platform')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Platform')
            ->has('groups.fees')
            ->where('groups.fees.withdraw_bps', 0)
            ->where('groups.fees.sms_alert_flat_minor', 600));

    $payload = [
        'group' => 'fees',
        'transfer_instant_bps' => 0,
        'transfer_instant_flat_minor' => 0,
        'transfer_protected_bps' => 50,
        'transfer_protected_flat_minor' => 0,
        'withdraw_bps' => 0,
        'withdraw_flat_minor' => 100_00,
        'deposit_bps' => 0,
        'deposit_flat_minor' => 0,
        'callback_bps' => 0,
        'callback_flat_minor' => 0,
        'listing_publish_bps' => 0,
        'listing_publish_flat_minor' => 0,
        'marketplace_sale_bps' => 100,
        'marketplace_sale_flat_minor' => 0,
        'recovery_bps' => 25,
        'recovery_flat_minor' => 0,
        'sms_alert_bps' => 0,
        'sms_alert_flat_minor' => 600,
    ];

    $this->actingAs($admin)->put('/admin/platform', $payload)->assertRedirect();

    expect(config('reton.fees.withdraw_flat_minor'))->toBe(100_00)
        ->and(config('reton.fees.recovery_bps'))->toBe(25)
        ->and(config('reton.recovery.fee_bps'))->toBe(25)
        ->and(config('reton.sms.alert_fee_minor'))->toBe(600);
});

it('calculates platform fees from bps and flat amounts', function () {
    config([
        'reton.fees.withdraw_bps' => 100,
        'reton.fees.withdraw_flat_minor' => 50_00,
    ]);

    $fee = app(PlatformFeeService::class)->calculate(
        FeeRail::Withdraw,
        Money::of(10_000_00, 'NGN'),
    );

    // 1% of 10,000 + ₦50 = ₦150
    expect($fee->amount)->toBe(150_00);
});

it('forbids non-admins from platform fees', function () {
    $user = readyUser();

    $this->actingAs($user)->get('/admin/platform')->assertForbidden();
});
