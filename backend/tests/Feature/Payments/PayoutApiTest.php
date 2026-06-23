<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\AlatpaySignatureVerifier;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Models\Payout;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway());
});

/**
 * @return array{0: User, 1: \App\Domain\Wallet\Models\Wallet}
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

it('settles a payout through the AlatPay webhook (transfer event)', function () {
    [$user, $wallet] = payoutUser(1_000_00);

    $providerRef = $this->actingAs($user)->postJson('/api/v1/payouts', [
        'wallet_id' => $wallet->id,
        'amount' => 400_00,
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'Ada Lovelace',
        'pin' => '1234',
    ])->json('data.provider_reference');

    $payload = json_encode([
        'id' => 'evt_transfer_1',
        'type' => 'transfer.completed',
        'data' => ['reference' => $providerRef, 'status' => 'completed'],
    ]);
    $signature = app(AlatpaySignatureVerifier::class)->sign($payload);

    $this->call('POST', '/api/v1/webhooks/alatpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_ALATPAY_SIGNATURE' => $signature,
    ], $payload)->assertOk();

    expect(Payout::where('provider_reference', $providerRef)->first()->status->value)->toBe('completed');
});
