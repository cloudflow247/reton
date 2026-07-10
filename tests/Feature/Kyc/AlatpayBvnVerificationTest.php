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

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function alatpayLiveConfig(array $overrides = []): array
{
    return array_merge([
        'services.alatpay.driver' => 'http',
        'services.alatpay.api_key' => 'bootstrap-key',
        'services.alatpay.merchant_email' => 'merchant@example.com',
        'services.alatpay.merchant_password' => 'secret-pass',
        'services.alatpay.business_id' => 'test-business',
        'services.alatpay.business_bvn' => '22109876543',
        'services.alatpay.base_url' => 'https://apibox.alatpay.ng',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $walletFakes
 */
function fakeAlatpayMerchantSession(array $walletFakes = [], string $businessId = 'test-business'): void
{
    Http::fake(array_merge([
        'apibox.alatpay.ng/merchant-onboarding/api/v1/auth/login' => Http::response([
            'status' => true,
            'message' => 'Success',
            'data' => [
                'token' => null,
                'businesses' => [[
                    'id' => $businessId,
                    'subscriptionPrimaryKey' => 'primary-from-login',
                ]],
            ],
        ], 200),
    ], $walletFakes));
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

    $account = \App\Domain\Payments\Models\StaticAccount::query()->where('user_id', $user->id)->first();
    expect($account)->not->toBeNull()
        ->and($account?->status->value)->toBe('active')
        ->and($account?->account_number)->not->toBeNull();
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
    config(alatpayLiveConfig());

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
        'services.alatpay.merchant_email' => '',
        'services.alatpay.merchant_password' => '',
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
    config(alatpayLiveConfig([
        'services.alatpay.base_url' => 'https://api.alatpay.ng',
    ]));

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    fakeAlatpayMerchantSession([
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

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => str_contains($request->url(), 'merchant-onboarding/api/v1/auth/login'));

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => str_contains($request->url(), 'apibox.alatpay.ng')
        && str_contains($request->url(), 'staticaccount')
        && ! str_contains($request->url(), 'api.alatpay.ng/')
        && $request->hasHeader('Ocp-Apim-Subscription-Key', 'primary-from-login'));
});

it('parses nested data null by falling back to root payload', function () {
    config(alatpayLiveConfig());

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    fakeAlatpayMerchantSession([
        'apibox.alatpay.ng/alatpay-wallet/*' => Http::response([
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
    config(alatpayLiveConfig([
        'services.alatpay.api_key' => 'public-key-by-mistake',
    ]));

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    fakeAlatpayMerchantSession([
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
    config(alatpayLiveConfig([
        'services.alatpay.business_id' => 'biz-static',
    ]));

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    fakeAlatpayMerchantSession([
        'apibox.alatpay.ng/alatpay-wallet/api/v1/staticaccount*' => Http::response([
            'hasStaticWallet' => false,
            'staticAccountResponses' => [],
        ], 200),
        'apibox.alatpay.ng/bank-transfer/*' => Http::response(['error' => 'should not be called'], 404),
    ], 'biz-static');

    $admin = \App\Models\User::factory()->create(['is_admin' => true]);

    app(\App\Domain\Settings\Services\PlatformSettingsService::class)->updateGroup('alatpay', [
        'driver' => 'http',
        'base_url' => 'https://apibox.alatpay.ng',
        'api_key' => 'bootstrap-key',
        'merchant_email' => 'merchant@example.com',
        'merchant_password' => 'secret-pass',
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

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $request->method() === 'GET'
            && str_contains($request->url(), 'alatpay-wallet/api/v1/staticaccount')
            && ! str_contains($request->url(), 'collectionhistory')
            && ($query['BusinessId'] ?? null) === 'biz-static'
            && (int) ($query['PageNumber'] ?? 0) === 1
            && (int) ($query['Status'] ?? 0) === 1
            && $request->hasHeader('Ocp-Apim-Subscription-Key', 'primary-from-login');
    });

    Http::assertNotSent(fn (\Illuminate\Http\Client\Request $request) => str_contains($request->url(), 'bank-transfer'));
});

it('matches official static-wallet create and validate payloads', function () {
    config(alatpayLiveConfig([
        'services.alatpay.business_id' => '8909d16f-e6bd-409f-7dce-08ddbd774ihj',
    ]));

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    fakeAlatpayMerchantSession([
        'apibox.alatpay.ng/alatpay-wallet/api/v1/staticaccount/validateAndCreate' => Http::response([
            'accountNumber' => '0412345678',
            'accountName' => 'Your Business – David_Mark',
            'id' => 'staticWalletid',
        ], 200),
        'apibox.alatpay.ng/alatpay-wallet/api/v1/staticaccount' => Http::response([
            'accountNumber' => null,
            'accountName' => null,
            'id' => '499d78ab-33ab-4f18-b9e9-65afe19ffccb',
            'message' => 'An OTP has been sent to 08*******86 and for verification. Kindly enter the OTP below.',
            'otpTrackingID' => 'a5c9c68f-44cc-40ef-8647-1c14d9b438cd',
        ], 200),
    ], '8909d16f-e6bd-409f-7dce-08ddbd774ihj');

    $gateway = app(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);

    $provision = $gateway->provisionStaticAccount(new \App\Domain\Payments\Alatpay\Data\StaticAccountRequest(
        walletType: 1,
        bvn: '22109876543',
        email: 'testmail@gmail.com',
    ));

    expect($provision->staticWalletId)->toBe('499d78ab-33ab-4f18-b9e9-65afe19ffccb')
        ->and($provision->otpTrackingId)->toBe('a5c9c68f-44cc-40ef-8647-1c14d9b438cd');

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => $request->method() === 'POST'
        && str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '/alatpay-wallet/api/v1/staticaccount')
        && $request['businessId'] === '8909d16f-e6bd-409f-7dce-08ddbd774ihj'
        && $request['staticWalletType'] === 1
        && $request['bvn'] === '22109876543'
        && $request['email'] === 'testmail@gmail.com'
        && $request->hasHeader('Ocp-Apim-Subscription-Key', 'primary-from-login'));

    $verified = $gateway->verifyStaticAccount(new \App\Domain\Payments\Alatpay\Data\StaticAccountVerifyRequest(
        staticWalletId: '499d78ab-33ab-4f18-b9e9-65afe19ffccb',
        otp: '332610',
        trackingId: 'a5c9c68f-44cc-40ef-8647-1c14d9b438cd',
    ));

    expect($verified->accountNumber)->toBe('0412345678');

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => $request->method() === 'POST'
        && str_contains($request->url(), 'validateAndCreate')
        && $request['staticWalletId'] === '499d78ab-33ab-4f18-b9e9-65afe19ffccb'
        && $request['businessId'] === '8909d16f-e6bd-409f-7dce-08ddbd774ihj'
        && $request['otp'] === '332610'
        && $request['trackingId'] === 'a5c9c68f-44cc-40ef-8647-1c14d9b438cd');
});

it('polls collectionhistory per official static-wallet docs', function () {
    config(alatpayLiveConfig([
        'services.alatpay.business_id' => 'biz-001',
    ]));

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    fakeAlatpayMerchantSession([
        'apibox.alatpay.ng/alatpay-wallet/api/v1/staticaccount/collectionhistory*' => Http::response([
            'staticAccountTransactionResponses' => [
                [
                    'staticAccountTransactionId' => 'txn-keep',
                    'status' => 1,
                    'accountNumber' => '041234245',
                    'amount' => 100.00,
                    'narration' => 'ALAT TRANSFER',
                ],
                [
                    'staticAccountTransactionId' => 'txn-skip',
                    'status' => 1,
                    'accountNumber' => '9999999999',
                    'amount' => 50.00,
                ],
            ],
        ], 200),
    ], 'biz-001');

    $txns = app(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class)
        ->fetchStaticAccountTransactions('041234245');

    expect($txns)->toHaveCount(1)
        ->and($txns[0]->transactionId)->toBe('txn-keep');

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), 'collectionhistory')
            && ($query['BusinessId'] ?? null) === 'biz-001'
            && (int) ($query['Status'] ?? 0) === 1;
    });
});

it('credits collection history rows marked Settled without numeric status 1', function () {
    config(alatpayLiveConfig([
        'services.alatpay.business_id' => 'biz-001',
    ]));

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    fakeAlatpayMerchantSession([
        'apibox.alatpay.ng/alatpay-wallet/api/v1/staticaccount/collectionhistory*' => Http::response([
            'staticAccountTransactionResponses' => [
                [
                    'staticAccountTransactionId' => 'txn-settled-string',
                    'settlementStatus' => 'Settled',
                    'accountNumber' => '0450041659',
                    'amount' => 100.00,
                    'narration' => 'IP:MOGAJI GABRIEL ROTIMI-NIP Transfer to CLOUDFLO',
                ],
                [
                    'staticAccountTransactionId' => 'txn-status-2',
                    'status' => 2,
                    'accountNumber' => '0450041659',
                    'amount' => 150.00,
                ],
            ],
        ], 200),
    ], 'biz-001');

    $txns = app(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class)
        ->fetchStaticAccountTransactions('0450041659', staticWalletId: 'wallet-uuid');

    expect($txns)->toHaveCount(2)
        ->and($txns[0]->isSuccessful())->toBeTrue()
        ->and($txns[1]->isSuccessful())->toBeTrue()
        ->and($txns[0]->amountMinor())->toBe(10000)
        ->and($txns[1]->amountMinor())->toBe(15000);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), 'collectionhistory')
            && ($query['StaticAccountId'] ?? null) === 'wallet-uuid';
    });
});

it('matches collection history when alatpay drops a leading zero on account number', function () {
    config(alatpayLiveConfig([
        'services.alatpay.business_id' => 'biz-001',
    ]));

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    fakeAlatpayMerchantSession([
        'apibox.alatpay.ng/alatpay-wallet/api/v1/staticaccount/collectionhistory*' => Http::response([
            'staticAccountTransactionResponses' => [
                [
                    'staticAccountTransactionId' => 'txn-leading-zero',
                    'status' => 1,
                    // JSON number — leading zero stripped vs Reton VA 0450041659
                    'accountNumber' => 450041659,
                    'amount' => 150.00,
                    'narration' => 'IP:MOGAJI GABRIEL ROTIMI-NIP Transfer to CLOUDFLO',
                    'transactionDate' => '2026-07-10T10:00:00',
                ],
            ],
        ], 200),
    ], 'biz-001');

    $txns = app(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class)
        ->fetchStaticAccountTransactions('0450041659');

    expect($txns)->toHaveCount(1)
        ->and($txns[0]->transactionId)->toBe('txn-leading-zero')
        ->and($txns[0]->amountMinor())->toBe(15000);
});

it('synthesizes a stable transaction id when alatpay omits staticAccountTransactionId', function () {
    config(alatpayLiveConfig([
        'services.alatpay.business_id' => 'biz-001',
    ]));

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    fakeAlatpayMerchantSession([
        'apibox.alatpay.ng/alatpay-wallet/api/v1/staticaccount/collectionhistory*' => Http::response([
            'staticAccountTransactionResponses' => [
                [
                    'status' => 1,
                    'accountNumber' => '0450041659',
                    'amount' => 150.00,
                    'narration' => 'NIP Transfer',
                    'transactionDate' => '2026-07-10T10:00:00',
                ],
            ],
        ], 200),
    ], 'biz-001');

    $gateway = app(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    $first = $gateway->fetchStaticAccountTransactions('0450041659');
    $second = $gateway->fetchStaticAccountTransactions('0450041659');

    expect($first)->toHaveCount(1)
        ->and($first[0]->transactionId)->toStartWith('sat-')
        ->and($second[0]->transactionId)->toBe($first[0]->transactionId);
});

it('pages through collection history when hasNext is true', function () {
    config(alatpayLiveConfig([
        'services.alatpay.business_id' => 'biz-001',
    ]));

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    fakeAlatpayMerchantSession([
        'apibox.alatpay.ng/alatpay-wallet/api/v1/staticaccount/collectionhistory*' => Http::sequence()
            ->push([
                'pagingData' => ['hasNext' => true, 'currentPage' => 1],
                'staticAccountTransactionResponses' => [
                    [
                        'staticAccountTransactionId' => 'txn-other',
                        'status' => 1,
                        'accountNumber' => '9999999999',
                        'amount' => 10.00,
                    ],
                ],
            ], 200)
            ->push([
                'pagingData' => ['hasNext' => false, 'currentPage' => 2],
                'staticAccountTransactionResponses' => [
                    [
                        'staticAccountTransactionId' => 'txn-page-2',
                        'status' => 1,
                        'accountNumber' => '0450041659',
                        'amount' => 150.00,
                    ],
                ],
            ], 200),
    ], 'biz-001');

    $txns = app(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class)
        ->fetchStaticAccountTransactions('0450041659');

    expect($txns)->toHaveCount(1)
        ->and($txns[0]->transactionId)->toBe('txn-page-2');
});

it('recovers existing individual VA when alatpay says bvn already used', function () {
    config(alatpayLiveConfig());

    app()->forgetInstance(\App\Domain\Payments\Alatpay\Contracts\AlatpayGateway::class);
    app()->forgetInstance(\App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway::class);

    $user = alatpayKycUser();

    fakeAlatpayMerchantSession([
        // Trailing * so GET ?PageNumber=… matches the same sequence as POST create.
        'apibox.alatpay.ng/alatpay-wallet/api/v1/staticaccount*' => Http::sequence()
            ->push([
                'status' => false,
                'message' => 'BVN has been used to create an individual static account for this business before',
            ], 400)
            ->push([
                'hasStaticWallet' => true,
                'staticAccountResponses' => [[
                    'id' => 'existing-wallet-1',
                    'walletType' => 1,
                    'status' => 1,
                    'accountNumber' => '0444652607',
                    'accountName' => 'CLOUDFLOW - USER',
                    'email' => $user->email,
                ]],
            ], 200),
    ]);

    $this->actingAs($user)->post('/profile/kyc/tier-2', [
        'bvn' => '22109876543',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money')
        ->assertSessionHas('success');

    $kyc = app(KycService::class)->forUser($user->fresh());
    expect($kyc->tier->value)->toBe(2)
        ->and($kyc->bvn_verified_at)->not->toBeNull();

    $account = \App\Domain\Payments\Models\StaticAccount::query()->where('user_id', $user->id)->first();
    expect($account)->not->toBeNull()
        ->and($account?->account_number)->toBe('0444652607')
        ->and($account?->status->value)->toBe('active');
});

it('still blocks bvn already linked to another reton user', function () {
    config(['services.alatpay.driver' => 'fake']);

    $owner = alatpayKycUser('Owner');
    $this->actingAs($owner)->post('/profile/kyc/tier-2', [
        'bvn' => '22109876543',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
    ]);
    $this->actingAs($owner)->post('/profile/kyc/tier-2/confirm', ['otp' => '123456']);

    $other = alatpayKycUser('Other');
    $this->actingAs($other)->from('/add-money')->post('/profile/kyc/tier-2', [
        'bvn' => '22109876543',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
        'return_to' => '/add-money',
    ])->assertRedirect('/add-money')
        ->assertSessionHasErrors('bvn');
});
