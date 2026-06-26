<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Services\PaymentRequestService;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway);
});

/** @return array{0: User, 1: Wallet} */
function apiRequester(): array
{
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    return [$user, $wallet];
}

it('creates a payment request and returns the link', function () {
    [$user, $wallet] = apiRequester();

    $this->actingAs($user)->postJson('/api/v1/payment-requests', [
        'wallet_id' => $wallet->id,
        'amount' => 250_00,
        'title' => 'Lunch money',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', 25000)
        ->assertJsonStructure(['data' => ['reference', 'payment_link_url']]);
});

it('forbids creating a request against a wallet the user does not own', function () {
    [, $wallet] = apiRequester();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->postJson('/api/v1/payment-requests', [
        'wallet_id' => $wallet->id,
        'amount' => 250_00,
        'title' => 'Nope',
    ])->assertStatus(403);
});

it('validates the create payload', function () {
    [$user] = apiRequester();

    $this->actingAs($user)->postJson('/api/v1/payment-requests', [
        'amount' => 0,
    ])->assertStatus(422);
});

it('lists only the callers own requests', function () {
    [$user, $wallet] = apiRequester();
    app(PaymentRequestService::class)->create($user, $wallet, Money::of(100_00, 'NGN'), 'Mine');
    [$other, $otherWallet] = apiRequester();
    app(PaymentRequestService::class)->create($other, $otherWallet, Money::of(100_00, 'NGN'), 'Theirs');

    $this->actingAs($user)->getJson('/api/v1/payment-requests')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('shows a public pay page without auth', function () {
    [$user, $wallet] = apiRequester();
    $request = app(PaymentRequestService::class)->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    $this->getJson('/api/v1/pay/'.$request->reference)
        ->assertOk()
        ->assertJsonPath('data.title', 'Lunch money')
        ->assertJsonPath('data.amount', 25000)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonStructure(['data' => ['payment_link_url']]);
});

it('cancels a pending request', function () {
    [$user, $wallet] = apiRequester();
    $request = app(PaymentRequestService::class)->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    $this->actingAs($user)->postJson('/api/v1/payment-requests/'.$request->id.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($request->fresh()->status)->toBe(PaymentRequestStatus::Cancelled);
});

it('forbids cancelling someone elses request', function () {
    [$user, $wallet] = apiRequester();
    $request = app(PaymentRequestService::class)->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->postJson('/api/v1/payment-requests/'.$request->id.'/cancel')
        ->assertStatus(403);
});

it('shows a payment request to its owner', function () {
    [$user, $wallet] = apiRequester();
    $request = app(PaymentRequestService::class)->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');

    $this->actingAs($user)->getJson('/api/v1/payment-requests/'.$request->id)
        ->assertOk()
        ->assertJsonPath('data.reference', $request->reference);
});

it('forbids viewing another users payment request', function () {
    [$user, $wallet] = apiRequester();
    $request = app(PaymentRequestService::class)->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch money');
    $other = User::factory()->create();

    $this->actingAs($other)->getJson('/api/v1/payment-requests/'.$request->id)
        ->assertStatus(403);
});
