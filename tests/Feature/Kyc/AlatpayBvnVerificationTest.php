<?php

declare(strict_types=1);

use App\Domain\Kyc\Models\KycVerificationLog;
use App\Domain\Kyc\Services\KycService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.kyc.bvn_provider' => 'alatpay',
        'services.alatpay.driver' => 'fake',
    ]);
});

function alatpayKycUser(string $name = 'Reton Test User'): User
{
    return User::factory()->create([
        'name' => $name,
        'transaction_pin' => Hash::make('1234'),
    ]);
}

it('initiates alatpay bvn verification and requires otp confirmation', function () {
    $user = alatpayKycUser();

    $this->actingAs($user)->post('/profile/kyc/tier-2', [
        'bvn' => '22334455667',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money')
        ->assertSessionHas('success');

    expect(app(KycService::class)->hasPendingAlatpayBvn($user))->toBeTrue();

    $this->actingAs($user)->post('/profile/kyc/tier-2/confirm', [
        'otp' => '123456',
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money')
        ->assertSessionHas('success');

    $kyc = app(KycService::class)->forUser($user->fresh());
    expect($kyc->tier->value)->toBe(2)
        ->and($kyc->bvn_verified_at)->not->toBeNull()
        ->and(KycVerificationLog::query()->where('user_id', $user->id)->where('status', 'success')->exists())->toBeTrue();
});

it('rejects invalid alatpay otp without upgrading tier', function () {
    $user = alatpayKycUser();

    $this->actingAs($user)->post('/profile/kyc/tier-2', [
        'bvn' => '22334455667',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
    ]);

    $this->actingAs($user)->from('/add-money')->post('/profile/kyc/tier-2/confirm', [
        'otp' => '000000',
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money')
        ->assertSessionHasErrors('otp');

    expect(app(KycService::class)->forUser($user->fresh())->tier->value)->toBe(1);
});

it('allows alatpay bvn verification without a transaction pin', function () {
    $user = User::factory()->create([
        'name' => 'Reton Test User',
        'transaction_pin' => null,
    ]);

    $this->actingAs($user)->post('/profile/kyc/tier-2', [
        'bvn' => '22334455667',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money');

    $this->actingAs($user)->post('/profile/kyc/tier-2/confirm', [
        'otp' => '123456',
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money');

    expect(app(KycService::class)->forUser($user->fresh())->tier->value)->toBe(2);
});

it('returns validation errors when alatpay credentials are missing', function () {
    config([
        'services.alatpay.driver' => 'http',
        'services.alatpay.api_key' => '',
        'services.alatpay.business_id' => '',
    ]);

    $user = alatpayKycUser();

    $this->actingAs($user)->from('/add-money')->post('/profile/kyc/tier-2', [
        'bvn' => '22334455667',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money')
        ->assertSessionHasErrors('bvn');
});
