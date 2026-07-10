<?php

declare(strict_types=1);

use App\Domain\Cards\Bridgecard\Gateways\FakeBridgecardVirtualCardGateway;
use App\Domain\Cards\Contracts\VirtualCardGateway;
use App\Domain\Cards\Enums\VirtualCardStatus;
use App\Domain\Cards\Models\VirtualCard;
use App\Domain\Cards\Services\VirtualCardService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.bridgecard.driver' => 'fake',
        'reton.features.cards' => true,
    ]);
    $this->app->instance(VirtualCardGateway::class, new FakeBridgecardVirtualCardGateway);
});

function cardUser(string $pin = '1234'): array
{
    $user = User::factory()->create([
        'phone' => '08030000000',
        'transaction_pin' => Hash::make($pin),
    ]);
    $wallet = app(WalletService::class)->open($user, 'NGN');
    app(WalletService::class)->fund($wallet, Money::of(500_000_00, 'NGN'));

    return [$user, $wallet];
}

it('renders the cards page without a card', function () {
    [$user] = cardUser();

    $this->actingAs($user)
        ->get('/cards')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Cards')
            ->where('cards.NGN', null)
            ->where('cardsReady', true));
});

it('issues an NGN virtual card with pin', function () {
    [$user, $wallet] = cardUser();

    $this->actingAs($user)
        ->post('/cards', [
            'wallet_id' => $wallet->id,
            'currency' => 'NGN',
            'pin' => '1234',
        ])
        ->assertRedirect(route('cards'));

    $card = VirtualCard::where('user_id', $user->id)->where('currency', 'NGN')->first();

    expect($card)->not->toBeNull()
        ->and($card->status)->toBe(VirtualCardStatus::Active)
        ->and($card->provider)->toBe('bridgecard')
        ->and($card->currency)->toBe('NGN')
        ->and(strlen($card->pan_last4))->toBe(4);
});

it('issues a USD virtual card funded via NGN wallet fx', function () {
    [$user, $wallet] = cardUser();

    $this->actingAs($user)
        ->post('/cards', [
            'wallet_id' => $wallet->id,
            'currency' => 'USD',
            'pin' => '1234',
        ])
        ->assertRedirect(route('cards'));

    $card = VirtualCard::where('user_id', $user->id)->where('currency', 'USD')->first();

    expect($card)->not->toBeNull()
        ->and($card->currency)->toBe('USD');
});

it('rejects issuing a second card for the same currency', function () {
    [$user, $wallet] = cardUser();

    app(VirtualCardService::class)->issue($user, $wallet, 'NGN');

    $this->actingAs($user)
        ->post('/cards', [
            'wallet_id' => $wallet->id,
            'currency' => 'NGN',
            'pin' => '1234',
        ])
        ->assertSessionHasErrors('pin');
});

it('reveals card details after pin verification', function () {
    [$user, $wallet] = cardUser();
    app(VirtualCardService::class)->issue($user, $wallet, 'NGN');

    $this->actingAs($user)
        ->postJson('/cards/reveal', ['pin' => '1234', 'currency' => 'NGN'])
        ->assertOk()
        ->assertJsonStructure(['pan', 'cvv', 'expiry', 'billing_address']);
});

it('freezes and unfreezes a card', function () {
    [$user, $wallet] = cardUser();
    $card = app(VirtualCardService::class)->issue($user, $wallet, 'NGN');

    $this->actingAs($user)
        ->post('/cards/freeze', ['pin' => '1234', 'currency' => 'NGN'])
        ->assertRedirect();

    expect($card->refresh()->status)->toBe(VirtualCardStatus::Blocked);

    $this->actingAs($user)
        ->post('/cards/unfreeze', ['pin' => '1234', 'currency' => 'NGN'])
        ->assertRedirect();

    expect($card->refresh()->status)->toBe(VirtualCardStatus::Active);
});

it('funds a card from wallet', function () {
    [$user, $wallet] = cardUser();
    $card = app(VirtualCardService::class)->issue($user, $wallet, 'NGN');

    $this->actingAs($user)
        ->post("/cards/{$card->id}/fund", [
            'wallet_id' => $wallet->id,
            'amount_minor' => 5_000_00,
            'pin' => '1234',
        ])
        ->assertRedirect();

    expect($card->refresh()->metadata['card_balance_minor'] ?? 0)->toBeGreaterThan(0);
});

it('returns fx quote for cross-currency funding', function () {
    [$user] = cardUser();

    $this->actingAs($user)
        ->getJson('/cards/fund/quote?source_currency=NGN&target_currency=USD&target_amount_minor=1000')
        ->assertOk()
        ->assertJsonStructure(['source_amount_minor', 'target_amount_minor', 'rate']);
});

it('shows coming soon when cards are disabled', function () {
    config(['reton.features.cards' => false]);
    [$user] = cardUser();

    $this->actingAs($user)
        ->get('/cards')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ComingSoon')
            ->where('feature', 'cards'));
});

it('rejects card issue when the feature is disabled', function () {
    config(['reton.features.cards' => false]);
    [$user, $wallet] = cardUser();

    $this->actingAs($user)
        ->post('/cards', [
            'wallet_id' => $wallet->id,
            'currency' => 'NGN',
            'pin' => '1234',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(VirtualCard::where('user_id', $user->id)->count())->toBe(0);
});
