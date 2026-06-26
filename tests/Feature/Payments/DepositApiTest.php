<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\AlatpaySignatureVerifier;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Services\AlatpayDepositService;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway);
});

/**
 * @return array{0: User, 1: Wallet}
 */
function apiDepositor(): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    return [$user, $wallet];
}

it('initiates a deposit and returns the virtual account to pay into', function () {
    [$user, $wallet] = apiDepositor();

    $this->actingAs($user)->postJson('/api/v1/deposits', [
        'wallet_id' => $wallet->id,
        'amount' => 500_00,
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', 50000)
        ->assertJsonStructure(['data' => ['virtual_account' => ['account_number', 'bank_name']]]);
});

it('forbids initiating a deposit into a wallet the user does not own', function () {
    [, $wallet] = apiDepositor();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->postJson('/api/v1/deposits', [
        'wallet_id' => $wallet->id,
        'amount' => 500_00,
    ])->assertStatus(403);
});

it('credits the wallet through the public webhook endpoint', function () {
    [$user, $wallet] = apiDepositor();
    $deposit = app(AlatpayDepositService::class)
        ->initiate($user, $wallet, Money::of(500_00, 'NGN'));

    $payload = json_encode([
        'id' => 'evt_api_1',
        'type' => 'transaction.completed',
        'data' => ['reference' => $deposit->provider_reference, 'amount' => 50000, 'currency' => 'NGN', 'status' => 'completed'],
    ]);
    $signature = app(AlatpaySignatureVerifier::class)->sign($payload);

    $this->call('POST', '/api/v1/webhooks/alatpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_ALATPAY_SIGNATURE' => $signature,
    ], $payload)->assertOk();

    expect($wallet->fresh()->balance)->toBe(50000)
        ->and(Deposit::find($deposit->id)->status->value)->toBe('completed');
});

it('rejects a webhook with a bad signature', function () {
    $payload = json_encode(['id' => 'evt_bad', 'data' => ['reference' => 'ALT-x', 'amount' => 1, 'status' => 'completed']]);

    $this->call('POST', '/api/v1/webhooks/alatpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_ALATPAY_SIGNATURE' => 'wrong',
    ], $payload)->assertStatus(401)->assertJsonPath('code', 'invalid_signature');
});

it('lists and shows a user\'s deposits', function () {
    [$user, $wallet] = apiDepositor();
    $deposit = app(AlatpayDepositService::class)
        ->initiate($user, $wallet, Money::of(500_00, 'NGN'));

    $this->actingAs($user)->getJson('/api/v1/deposits')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);

    $this->actingAs($user)->getJson("/api/v1/deposits/{$deposit->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $deposit->id);
});
