<?php

declare(strict_types=1);

use App\Domain\Kyc\Models\KycVerificationLog;
use App\Domain\Kyc\Services\KycService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

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

it('confirms alatpay otp after a fresh gateway instance (cache-backed fake)', function () {
    $user = alatpayKycUser();

    $this->actingAs($user)->post('/profile/kyc/tier-2', [
        'bvn' => '22334455667',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money');

    // Simulate a new HTTP request: forget the singleton and rebind a clean fake.
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway::class);

    $this->actingAs($user)->post('/profile/kyc/tier-2/confirm', [
        'otp' => '123456',
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money')
        ->assertSessionHas('success');

    expect(app(KycService::class)->forUser($user->fresh())->tier->value)->toBe(2);
});

it('exposes pending otp props on profile for the verification gate', function () {
    $user = alatpayKycUser();

    $this->actingAs($user)->post('/profile/kyc/tier-2', [
        'bvn' => '22334455667',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
        'return_to' => '/profile',
    ])->assertRedirect('/profile');

    $this->actingAs($user)->get('/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Profile')
            ->where('bvnPendingOtp', true)
            ->where('bvnProvider', 'alatpay')
            ->where('bvnDemoMode', true)
        );
});

it('rejects known demo bvns when alatpay is live', function () {
    config([
        'services.alatpay.driver' => 'http',
        'services.alatpay.api_key' => 'test-key',
        'services.alatpay.business_id' => 'test-business',
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

it('returns validation errors when alatpay credentials are missing', function () {
    config([
        'services.alatpay.driver' => 'http',
        'services.alatpay.api_key' => '',
        'services.alatpay.business_id' => '',
    ]);

    $user = alatpayKycUser();

    $this->actingAs($user)->from('/add-money')->post('/profile/kyc/tier-2', [
        'bvn' => '22109876543',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money')
        ->assertSessionHasErrors('bvn');
});

it('parses flat alatpay otp response and remaps legacy api host', function () {
    config([
        'services.alatpay.driver' => 'http',
        'services.alatpay.api_key' => 'test-key',
        'services.alatpay.business_id' => 'test-business',
        // Legacy misconfig — gateway must call apibox instead.
        'services.alatpay.base_url' => 'https://api.alatpay.ng',
    ]);

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    Http::fake([
        'apibox.alatpay.ng/alatpay-wallet/api/v1/staticaccount' => Http::response([
            'id' => 'wallet-flat-1',
            'otpTrackingID' => 'track-flat-9',
            'message' => 'OTP has been sent to the phone number linked to the BVN',
        ], 200),
        'api.alatpay.ng/*' => Http::response(['error' => 'wrong host'], 404),
    ]);

    $user = alatpayKycUser();

    $this->actingAs($user)->post('/profile/kyc/tier-2', [
        'bvn' => '22109876543',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money')
        ->assertSessionHas('success');

    expect(app(KycService::class)->hasPendingAlatpayBvn($user))->toBeTrue();

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => str_contains($request->url(), 'apibox.alatpay.ng')
        && str_contains($request->url(), 'staticaccount')
        && ! str_contains($request->url(), 'api.alatpay.ng/'));
});

it('parses nested data null by falling back to root payload', function () {
    config([
        'services.alatpay.driver' => 'http',
        'services.alatpay.api_key' => 'test-key',
        'services.alatpay.business_id' => 'test-business',
        'services.alatpay.base_url' => 'https://apibox.alatpay.ng',
    ]);

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    Http::fake([
        'apibox.alatpay.ng/*' => Http::response([
            'data' => null,
            'id' => 'wallet-root-2',
            'otpTrackingID' => 'track-root-2',
            'message' => 'OTP sent',
        ], 200),
    ]);

    $user = alatpayKycUser();

    $this->actingAs($user)->post('/profile/kyc/tier-2', [
        'bvn' => '22109876543',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money')
        ->assertSessionHas('success');

    expect(app(KycService::class)->hasPendingAlatpayBvn($user))->toBeTrue();
});

it('surfaces secret-key guidance when static wallet returns 401', function () {
    config([
        'services.alatpay.driver' => 'http',
        'services.alatpay.api_key' => 'public-key-by-mistake',
        'services.alatpay.business_id' => 'test-business',
        'services.alatpay.base_url' => 'https://apibox.alatpay.ng',
    ]);

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    Http::fake([
        'apibox.alatpay.ng/alatpay-wallet/*' => Http::response([
            'statusCode' => 401,
            'message' => 'Access denied due to invalid subscription key or wrong API endpoint.',
        ], 401),
    ]);

    $user = alatpayKycUser();

    $this->actingAs($user)->from('/add-money')->post('/profile/kyc/tier-2', [
        'bvn' => '22109876543',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money')
        ->assertSessionHasErrors('bvn');

    $errors = session('errors')->getBag('default')->get('bvn');
    expect($errors[0])->toContain('Access denied');
});

it('admin alatpay test hits static wallet not bank-transfer', function () {
    config([
        'services.alatpay.driver' => 'http',
        'services.alatpay.api_key' => 'secret-key',
        'services.alatpay.business_id' => 'biz-static',
        'services.alatpay.base_url' => 'https://apibox.alatpay.ng',
        'services.alatpay.business_bvn' => '22109876543',
    ]);

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    Http::fake([
        'apibox.alatpay.ng/alatpay-wallet/api/v1/staticaccount*' => Http::response([
            'hasStaticWallet' => false,
            'staticAccountResponses' => [],
        ], 200),
        'apibox.alatpay.ng/bank-transfer/*' => Http::response(['error' => 'should not be called'], 404),
    ]);

    $admin = \App\Models\User::factory()->create(['is_admin' => true]);

    // Ensure settings ready check passes
    app(\App\Domain\Settings\Services\PlatformSettingsService::class)->updateGroup('alatpay', [
        'driver' => 'http',
        'base_url' => 'https://apibox.alatpay.ng',
        'api_key' => 'secret-key',
        'business_id' => 'biz-static',
        'business_bvn' => '22109876543',
        'webhook_secret' => '',
        'timeout' => 15,
    ], $admin);

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);

    $this->actingAs($admin)->post('/admin/integrations/alatpay/test')
        ->assertRedirect()
        ->assertSessionHas('success')
        ->assertSessionMissing('error');

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => str_contains($request->url(), 'alatpay-wallet/api/v1/staticaccount')
        && $request->method() === 'GET');

    Http::assertNotSent(fn (\Illuminate\Http\Client\Request $request) => str_contains($request->url(), 'bank-transfer'));
});
