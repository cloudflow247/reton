<?php

declare(strict_types=1);

use App\Domain\Payments\Contracts\PayoutGateway;
use App\Domain\Payments\Enums\PayoutStatus;
use App\Domain\Payments\Models\Payout;
use App\Domain\Payments\Paystack\Gateways\FakePaystackPayoutGateway;
use App\Domain\Payments\Paystack\PaystackSignatureVerifier;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'reton.payouts.provider' => 'paystack',
        'reton.features.withdraw' => true,
        'services.paystack.driver' => 'fake',
        'services.paystack.webhook_secret' => 'test-paystack-webhook',
    ]);

    $this->app->instance(PayoutGateway::class, new FakePaystackPayoutGateway);
});

it('settles a payout through the Paystack transfer.success webhook', function () {
    $user = User::factory()->create(['transaction_pin' => Hash::make('1234')]);
    $wallet = app(WalletService::class)->open($user, 'NGN');
    app(WalletService::class)->fund($wallet, Money::of(1_000_00, 'NGN'));

    $providerRef = $this->actingAs($user)->postJson('/api/v1/payouts', [
        'wallet_id' => $wallet->id,
        'amount' => 400_00,
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => $user->name,
        'pin' => '1234',
    ])->assertCreated()->json('data.provider_reference');

    $payout = Payout::query()->where('provider_reference', $providerRef)->firstOrFail();

    $payload = json_encode([
        'event' => 'transfer.success',
        'data' => [
            'id' => 991122,
            'transfer_code' => $providerRef,
            'reference' => $payout->reference,
            'amount' => 40000,
            'status' => 'success',
            'currency' => 'NGN',
        ],
    ], JSON_THROW_ON_ERROR);

    $signature = app(PaystackSignatureVerifier::class)->sign($payload);

    $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
    ], $payload)->assertOk();

    expect($payout->fresh()->status)->toBe(PayoutStatus::Completed);
});

it('rejects Paystack webhooks with an invalid signature', function () {
    $payload = json_encode([
        'event' => 'transfer.success',
        'data' => ['transfer_code' => 'TRF_x', 'reference' => 'PO-X', 'status' => 'success'],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => 'deadbeef',
    ], $payload)->assertStatus(401);
});
