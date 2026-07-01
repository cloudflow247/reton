<?php

declare(strict_types=1);

use App\Domain\Callback\Enums\CallbackResolution;
use App\Domain\Callback\Services\CallbackDecisionEngine;
use App\Domain\Callback\Services\CallbackService;
use App\Domain\Marketplace\Enums\DigitalDisputeCategory;
use App\Domain\Marketplace\Enums\DigitalOrderStatus;
use App\Domain\Marketplace\Exceptions\MarketplaceException;
use App\Domain\Marketplace\Models\DigitalListing;
use App\Domain\Marketplace\Models\DigitalOrder;
use App\Domain\Marketplace\Services\DigitalEscrowJudgementService;
use App\Domain\Marketplace\Services\DigitalMarketplaceService;
use App\Domain\Marketplace\Support\ListingLinks;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function seedDigitalOrder(): array
{
    $seller = User::factory()->create(['transaction_pin' => Hash::make('1234')]);
    $buyer = User::factory()->create(['transaction_pin' => Hash::make('1234')]);

    app(WalletService::class)->open($seller, 'NGN');
    $buyerWallet = app(WalletService::class)->open($buyer, 'NGN');
    app(WalletService::class)->fund($buyerWallet, Money::of(50_000_00, 'NGN'));

    $listing = app(DigitalMarketplaceService::class)->createListing(
        $seller,
        'Premium preset pack',
        'Lightroom presets for product photography.',
        Money::of(10_000_00, 'NGN'),
        'DOWNLOAD-LINK-XYZ',
    );

    $order = app(DigitalMarketplaceService::class)->purchase($buyer, $listing, $buyerWallet->refresh());

    return compact('seller', 'buyer', 'listing', 'order');
}

it('purchases a digital listing with a protected transfer', function () {
    ['order' => $order, 'listing' => $listing] = seedDigitalOrder();

    expect($order->status)->toBe(DigitalOrderStatus::PaidHeld)
        ->and($order->transfer_id)->not->toBeNull()
        ->and($order->transfer?->status)->toBe(TransferStatus::Held)
        ->and($order->transfer?->metadata['purpose'])->toBe('digital_item')
        ->and($order->transfer?->hold?->expires_at)->toBeNull()
        ->and($order->delivery_deadline_at)->not->toBeNull()
        ->and($listing->refresh()->status->value)->toBe('sold');
});

it('requires delivery before the buyer can release payment', function () {
    ['seller' => $seller, 'buyer' => $buyer, 'order' => $order] = seedDigitalOrder();
    $transfer = $order->transfer;

    expect(fn () => app(DigitalMarketplaceService::class)->assertBuyerCanRelease($transfer))
        ->toThrow(MarketplaceException::class);

    app(DigitalMarketplaceService::class)->deliver($order->fresh(), $seller, true);

    app(DigitalMarketplaceService::class)->assertBuyerCanRelease($transfer->fresh());

    app(TransferService::class)->release($transfer->fresh());

    expect($order->fresh()->status)->toBe(DigitalOrderStatus::Completed)
        ->and($transfer->fresh()->status)->toBe(TransferStatus::Completed);
});

it('confirms satisfaction and releases payment in one step', function () {
    ['seller' => $seller, 'buyer' => $buyer, 'order' => $order] = seedDigitalOrder();

    app(DigitalMarketplaceService::class)->deliver($order->fresh(), $seller, true);

    $updated = app(DigitalMarketplaceService::class)->confirmSatisfaction($order->fresh(), $buyer);

    expect($updated->status)->toBe(DigitalOrderStatus::Completed)
        ->and($updated->buyer_satisfied)->toBeTrue()
        ->and($order->transfer?->fresh()->status)->toBe(TransferStatus::Completed);
});

it('requires seller attestation when delivering', function () {
    ['seller' => $seller, 'order' => $order] = seedDigitalOrder();

    expect(fn () => app(DigitalMarketplaceService::class)->deliver($order, $seller, false))
        ->toThrow(MarketplaceException::class);
});

it('blocks not-delivered disputes during the grace period', function () {
    ['buyer' => $buyer, 'order' => $order] = seedDigitalOrder();

    expect(fn () => app(DigitalMarketplaceService::class)->raiseDispute(
        $order,
        $buyer,
        DigitalDisputeCategory::NotDelivered,
        'Seller has not sent anything yet.',
    ))->toThrow(MarketplaceException::class);

    $this->travel(25)->hours();

    $callback = app(DigitalMarketplaceService::class)->raiseDispute(
        $order->fresh(),
        $buyer,
        DigitalDisputeCategory::NotDelivered,
        'Still no delivery after waiting.',
    );

    expect($callback->transfer_id)->toBe($order->transfer_id)
        ->and($order->fresh()->status)->toBe(DigitalOrderStatus::Disputed)
        ->and($order->fresh()->dispute_category)->toBe('not_delivered');
});

it('requires structured disputes after delivery', function () {
    ['seller' => $seller, 'buyer' => $buyer, 'order' => $order] = seedDigitalOrder();

    app(DigitalMarketplaceService::class)->deliver($order->fresh(), $seller, true);

    expect(fn () => app(DigitalMarketplaceService::class)->assertCanInitiateGenericCallback(
        $order->fresh(),
        $buyer,
        'Generic reason',
    ))->toThrow(MarketplaceException::class);

    $callback = app(DigitalMarketplaceService::class)->raiseDispute(
        $order->fresh(),
        $buyer,
        DigitalDisputeCategory::InvalidItem,
        'The license key returns invalid when I try to activate it.',
    );

    expect($callback->reason)->toContain('Key or link does not work')
        ->and($order->fresh()->dispute_category)->toBe('invalid_item');
});

it('refunds buyers when a not-delivered callback expires unanswered', function () {
    ['buyer' => $buyer, 'order' => $order] = seedDigitalOrder();

    $this->travel(25)->hours();

    $callback = app(DigitalMarketplaceService::class)->raiseDispute(
        $order->fresh(),
        $buyer,
        DigitalDisputeCategory::NotDelivered,
        'No delivery received.',
    );

    $resolution = app(CallbackDecisionEngine::class)->resolveOnExpiry($callback->fresh());

    expect($resolution)->toBe(CallbackResolution::Refund);
});

it('auto-refunds overdue undelivered orders and restores the buyer wallet', function () {
    ['seller' => $seller, 'buyer' => $buyer, 'order' => $order] = seedDigitalOrder();
    $buyerWallet = app(WalletService::class)->open($buyer, 'NGN');
    $sellerWallet = app(WalletService::class)->open($seller, 'NGN');

    expect($buyerWallet->fresh()->balance)->toBe(40_000_00)
        ->and($sellerWallet->fresh()->balance)->toBe(10_000_00)
        ->and($sellerWallet->fresh()->held_balance)->toBe(10_000_00);

    $this->travel(73)->hours();

    $refunded = app(DigitalMarketplaceService::class)->refundOverdueUndelivered($order->fresh());

    expect($refunded)->toBeTrue()
        ->and($order->fresh()->status)->toBe(DigitalOrderStatus::Refunded)
        ->and($order->fresh()->dispute_category)->toBe('not_delivered')
        ->and($order->transfer?->fresh()->status)->toBe(TransferStatus::Refunded)
        ->and($buyerWallet->fresh()->balance)->toBe(50_000_00)
        ->and($sellerWallet->fresh()->balance)->toBe(0)
        ->and($sellerWallet->fresh()->held_balance)->toBe(0);
});

it('does not auto-refund before the delivery deadline', function () {
    ['order' => $order] = seedDigitalOrder();

    expect(app(DigitalMarketplaceService::class)->refundOverdueUndelivered($order))->toBeFalse()
        ->and($order->fresh()->status)->toBe(DigitalOrderStatus::PaidHeld);
});

it('does not auto-refund when an open callback is handling the order', function () {
    ['buyer' => $buyer, 'order' => $order] = seedDigitalOrder();

    $this->travel(25)->hours();

    app(DigitalMarketplaceService::class)->raiseDispute(
        $order->fresh(),
        $buyer,
        DigitalDisputeCategory::NotDelivered,
        'Still waiting on delivery.',
    );

    $this->travel(48)->hours();

    expect(app(DigitalMarketplaceService::class)->refundOverdueUndelivered($order->fresh()))->toBeFalse()
        ->and($order->fresh()->status)->toBe(DigitalOrderStatus::Disputed)
        ->and($order->transfer?->fresh()->status)->toBe(TransferStatus::Held);
});

it('exposes auto-refund timing in escrow guidance for buyers waiting on delivery', function () {
    ['buyer' => $buyer, 'order' => $order] = seedDigitalOrder();

    $guidance = app(DigitalEscrowJudgementService::class)->guidanceFor($order->fresh(), $buyer);

    expect($guidance['auto_refund_at'])->toEqual($order->delivery_deadline_at);
});

it('exposes delivery content only to the buyer after delivery', function () {
    ['seller' => $seller, 'buyer' => $buyer, 'order' => $order] = seedDigitalOrder();
    $service = app(DigitalMarketplaceService::class);

    expect($service->deliveryPayloadForBuyer($order, $seller))->toBeNull()
        ->and($service->deliveryPayloadForBuyer($order, $buyer))->toBeNull();

    app(DigitalMarketplaceService::class)->deliver($order->fresh(), $seller, true);

    $payload = $service->deliveryPayloadForBuyer($order->fresh(), $buyer);

    expect($payload['content'])->toBe('DOWNLOAD-LINK-XYZ')
        ->and($payload['integrity_verified'])->toBeTrue()
        ->and($payload['description'])->toContain('Lightroom');
});

it('scores seller trust from completed orders', function () {
    ['seller' => $seller] = seedDigitalOrder();

    expect(app(DigitalEscrowJudgementService::class)->sellerTrustScore($seller))->toBe(70);
});

it('renders the marketplace page with listings', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    app(DigitalMarketplaceService::class)->createListing(
        $other,
        'Beat pack',
        'Afrobeats loops.',
        Money::of(3_000_00, 'NGN'),
        'BEATS-URL',
    );

    $this->actingAs($user)->get('/marketplace')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketplace')
            ->has('listings', 1));
});

it('confirms an order over the web with a pin', function () {
    ['seller' => $seller, 'buyer' => $buyer, 'order' => $order] = seedDigitalOrder();

    app(DigitalMarketplaceService::class)->deliver($order->fresh(), $seller, true);

    $this->actingAs($buyer)
        ->post("/marketplace/orders/{$order->id}/confirm", ['pin' => '1234'])
        ->assertRedirect(route('protection'));

    expect($order->fresh()->status)->toBe(DigitalOrderStatus::Completed);
});

function seedActiveListing(): array
{
    $seller = User::factory()->create(['transaction_pin' => Hash::make('1234')]);
    app(WalletService::class)->open($seller, 'NGN');

    $listing = app(DigitalMarketplaceService::class)->createListing(
        $seller,
        'Premium preset pack',
        'Lightroom presets for product photography.',
        Money::of(10_000_00, 'NGN'),
        'DOWNLOAD-LINK-XYZ',
    );

    return compact('seller', 'listing');
}

it('builds stable listing share urls for web and mobile deep links', function () {
    ['listing' => $listing] = seedActiveListing();

    config([
        'reton.links.public_base' => 'https://reton.ng',
        'reton.links.listing_path' => '/l',
        'reton.links.app_scheme' => 'reton',
    ]);

    expect(ListingLinks::web($listing))->toBe('https://reton.ng/l/'.$listing->id)
        ->and(ListingLinks::app($listing))->toBe('reton://l/'.$listing->id);
});

it('shows a shareable listing page to guests with purchase disabled', function () {
    ['listing' => $listing] = seedActiveListing();

    $this->get(route('marketplace.listings.show', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Marketplace/ListingShow')
            ->where('listing.id', $listing->id)
            ->where('listing.can_purchase', false)
            ->where('listing.is_owner', false)
            ->where('listing.share_url', ListingLinks::web($listing)));
});

it('lets the seller share from their listing page', function () {
    ['seller' => $seller, 'listing' => $listing] = seedActiveListing();

    $this->actingAs($seller)
        ->get(route('marketplace.listings.show', $listing))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('listing.is_owner', true)
            ->where('listing.can_purchase', false));
});

it('redirects back to the listing after login with a redirect query', function () {
    ['listing' => $listing] = seedActiveListing();
    $buyer = User::factory()->create(['transaction_pin' => Hash::make('1234')]);

    $this->get('/login?redirect=/l/'.$listing->id)->assertOk();

    $this->post('/login', [
        'email' => $buyer->email,
        'password' => 'password',
    ])->assertRedirect('/l/'.$listing->id);
});

it('purchases a listing from the share page', function () {
    ['seller' => $seller, 'listing' => $listing] = seedActiveListing();
    $buyer = User::factory()->create(['transaction_pin' => Hash::make('1234')]);
    $buyerWallet = app(WalletService::class)->open($buyer, 'NGN');
    app(WalletService::class)->fund($buyerWallet, Money::of(50_000_00, 'NGN'));

    $this->actingAs($buyer)
        ->post(route('marketplace.listings.purchase', $listing), ['pin' => '1234'])
        ->assertRedirect(route('protection'));

    expect(DigitalOrder::query()->where('listing_id', $listing->id)->exists())->toBeTrue();
});

it('forbids viewing sold listings except by the seller', function () {
    ['seller' => $seller, 'listing' => $listing] = seedActiveListing();
    $buyer = User::factory()->create(['transaction_pin' => Hash::make('1234')]);
    $buyerWallet = app(WalletService::class)->open($buyer, 'NGN');
    app(WalletService::class)->fund($buyerWallet, Money::of(50_000_00, 'NGN'));
    app(DigitalMarketplaceService::class)->purchase($buyer, $listing, $buyerWallet);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('marketplace.listings.show', $listing->fresh()))
        ->assertForbidden();

    $this->actingAs($seller)
        ->get(route('marketplace.listings.show', $listing->fresh()))
        ->assertOk();
});

it('redirects to the share page after publishing a listing', function () {
    ['seller' => $seller] = seedActiveListing();

    $response = $this->actingAs($seller)->post('/marketplace/listings', [
        'title' => 'UI kit bundle',
        'description' => 'Figma components for mobile banking apps.',
        'price' => 5_000_00,
        'delivery_payload' => 'https://files.example.com/ui-kit.zip',
    ]);

    $listing = DigitalListing::query()->where('title', 'UI kit bundle')->firstOrFail();

    $response->assertRedirect(route('marketplace.listings.show', $listing));
});

it('serves mobile association files for listing deep links', function () {
    config([
        'reton.links.listing_path' => '/l',
        'reton.links.mobile.ios_bundle_id' => 'ng.reton.app',
        'reton.links.mobile.apple_team_id' => 'TEAM123',
    ]);

    $this->get('/.well-known/apple-app-site-association')
        ->assertOk()
        ->assertJsonPath('applinks.details.0.appID', 'TEAM123.ng.reton.app')
        ->assertJsonPath('applinks.details.0.paths.0', '/l/*');
});
