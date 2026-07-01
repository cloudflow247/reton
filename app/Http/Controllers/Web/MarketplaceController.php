<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Auth\Services\PinService;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Exceptions\FraudBlockedException;
use App\Domain\Fraud\Services\FraudService;
use App\Domain\Marketplace\Enums\DigitalDisputeCategory;
use App\Domain\Marketplace\Models\DigitalListing;
use App\Domain\Marketplace\Models\DigitalOrder;
use App\Domain\Marketplace\Services\DigitalMarketplaceService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\VerifiesPin;
use App\Http\Requests\Web\Marketplace\ConfirmDigitalOrderRequest;
use App\Http\Requests\Web\Marketplace\DeliverDigitalOrderRequest;
use App\Http\Requests\Web\Marketplace\PurchaseDigitalListingRequest;
use App\Http\Requests\Web\Marketplace\RaiseDigitalDisputeRequest;
use App\Http\Requests\Web\Marketplace\StoreDigitalListingRequest;
use App\Http\Resources\Api\V1\DigitalListingResource;
use App\Http\Resources\Api\V1\DigitalOrderResource;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    use VerifiesPin;

    public function __construct(
        private readonly DigitalMarketplaceService $marketplace,
        private readonly PinService $pins,
        private readonly FraudService $fraud,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $listings = DigitalListing::query()
            ->where('status', 'active')
            ->where('seller_id', '!=', $user->getKey())
            ->with('seller:id,name')
            ->latest()
            ->get();

        $myListings = DigitalListing::query()
            ->where('seller_id', $user->getKey())
            ->latest()
            ->get();

        $orders = DigitalOrder::query()
            ->where(fn ($q) => $q->where('buyer_id', $user->getKey())->orWhere('seller_id', $user->getKey()))
            ->with(['listing', 'transfer.hold'])
            ->latest()
            ->get();

        return Inertia::render('Marketplace', [
            'listings' => DigitalListingResource::collection($listings),
            'myListings' => DigitalListingResource::collection($myListings),
            'orders' => DigitalOrderResource::collection($orders),
        ]);
    }

    public function store(StoreDigitalListingRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('create', DigitalListing::class);

        $currency = (string) config('reton.default_currency', 'NGN');

        $listing = $this->marketplace->createListing(
            $user,
            $request->string('title')->toString(),
            $request->string('description')->toString(),
            Money::of($request->integer('price'), $currency),
            $request->string('delivery_payload')->toString(),
        );

        return redirect()
            ->route('marketplace.listings.show', $listing)
            ->with('success', 'Listing published — share the link with your buyer.');
    }

    public function show(Request $request, DigitalListing $listing): Response
    {
        $this->authorize('view', $listing);

        $listing->load('seller:id,name');

        return Inertia::render('Marketplace/ListingShow', [
            'listing' => new DigitalListingResource($listing),
        ]);
    }

    public function purchase(PurchaseDigitalListingRequest $request, DigitalListing $listing): RedirectResponse
    {
        $this->authorize('purchase', $listing);

        /** @var User $user */
        $user = $request->user();
        $wallet = $user->wallets()->firstOrFail();

        $this->verifyPin($this->pins, $user, $request->string('pin')->toString());

        $amount = Money::of($listing->price, $listing->currency);

        $assessment = $this->fraud->evaluate(new FraudContext(
            user: $user,
            wallet: $wallet,
            amount: $amount,
            action: 'transfer',
            beneficiary: null,
            deviceFingerprint: $request->header('X-Device-Fingerprint'),
            ipAddress: $request->ip(),
        ));

        if ($assessment->isBlocked()) {
            throw FraudBlockedException::make();
        }

        $this->marketplace->purchase($user, $listing, $wallet);

        return redirect()
            ->route('protection')
            ->with('success', 'Purchase protected — funds held until you confirm delivery.');
    }

    public function deliver(DeliverDigitalOrderRequest $request, DigitalOrder $order): RedirectResponse
    {
        $this->authorize('deliver', $order);

        /** @var User $user */
        $user = $request->user();

        $this->marketplace->deliver($order, $user, true);

        return back()->with('success', 'Digital item delivered — buyer can now review and confirm.');
    }

    public function confirm(ConfirmDigitalOrderRequest $request, DigitalOrder $order): RedirectResponse
    {
        $this->authorize('confirm', $order);

        /** @var User $user */
        $user = $request->user();
        $this->verifyPin($this->pins, $user, $request->string('pin')->toString());

        $this->marketplace->confirmSatisfaction($order, $user);

        return redirect()
            ->route('protection')
            ->with('success', 'Item confirmed — payment released to the seller.');
    }

    public function dispute(RaiseDigitalDisputeRequest $request, DigitalOrder $order): RedirectResponse
    {
        $this->authorize('dispute', $order);

        /** @var User $user */
        $user = $request->user();
        $this->verifyPin($this->pins, $user, $request->string('pin')->toString());

        $category = DigitalDisputeCategory::from($request->string('category')->toString());

        $this->marketplace->raiseDispute(
            $order,
            $user,
            $category,
            $request->string('details')->toString(),
        );

        return redirect()
            ->route('protection')
            ->with('success', 'Dispute opened — the seller has been notified to respond.');
    }
}
