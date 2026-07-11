<?php

declare(strict_types=1);

use App\Domain\Payments\Contracts\PayoutGateway;
use App\Domain\Payments\Models\Payout;
use App\Domain\Payments\Paystack\Gateways\FakePaystackPayoutGateway;
use App\Domain\Payments\Paystack\PaystackSignatureVerifier;
use App\Domain\Wallet\Models\Wallet;
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

/**
 * @return array{0: User, 1: Wallet}
 */
function payoutUser(int $fund = 1_000_00): array
{
    $user = User::factory()->create(['transaction_pin' => Hash::make('1234')]);
    $wallet = app(WalletService::class)->open($user, 'NGN');
    app(WalletService::class)->fund($wallet, Money::of($fund, 'NGN'));

    return [$user, $wallet->refresh()];
}

it('requests a payout with a valid pin and reserves the funds', function () {
    [$user, $wallet] = payoutUser(1_000_00);

    $this->actingAs($user)->postJson('/api/v1/payouts', [
        'wallet_id' => $wallet->id,
        'amount' => 400_00,
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'Ada Lovelace',
        'pin' => '1234',
    ])->assertCreated()->assertJsonPath('data.status', 'pending');

    expect($wallet->fresh()->balance)->toBe(60000)
        ->and(Payout::where('user_id', $user->id)->exists())->toBeTrue();
});

it('rejects a payout with the wrong pin and does not reserve funds', function () {
    [$user, $wallet] = payoutUser(1_000_00);

    $this->actingAs($user)->postJson('/api/v1/payouts', [
        'wallet_id' => $wallet->id,
        'amount' => 400_00,
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'Ada Lovelace',
        'pin' => '0000',
    ])->assertStatus(422)->assertJsonPath('code', 'invalid_pin');

    expect($wallet->fresh()->balance)->toBe(100000);
});

it('settles a payout through the Paystack webhook (transfer.success)', function () {
    [$user, $wallet] = payoutUser(1_000_00);

    $response = $this->actingAs($user)->postJson('/api/v1/payouts', [
        'wallet_id' => $wallet->id,
        'amount' => 400_00,
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'Ada Lovelace',
        'pin' => '1234',
    ])->assertCreated();

    $providerRef = $response->json('data.provider_reference');
    $reference = $response->json('data.reference');

    $payload = json_encode([
        'event' => 'transfer.success',
        'data' => [
            'id' => 'evt_transfer_1',
            'transfer_code' => $providerRef,
            'reference' => $reference,
            'status' => 'success',
        ],
    ], JSON_THROW_ON_ERROR);
    $signature = app(PaystackSignatureVerifier::class)->sign($payload);

    $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
    ], $payload)->assertOk();

    expect(Payout::where('provider_reference', $providerRef)->first()->status->value)->toBe('completed');
});

it('blocks payouts when the withdraw feature is disabled', function () {
    config(['reton.features.withdraw' => false]);
    [$user, $wallet] = payoutUser(1_000_00);

    $this->actingAs($user)->postJson('/api/v1/payouts', [
        'wallet_id' => $wallet->id,
        'amount' => 400_00,
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'Ada Lovelace',
        'pin' => '1234',
    ])->assertStatus(503)->assertJsonPath('code', 'feature_disabled');

    expect($wallet->fresh()->balance)->toBe(100000);
});
