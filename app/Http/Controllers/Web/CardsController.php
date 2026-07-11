<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Auth\Services\PinService;
use App\Domain\Cards\Exceptions\VirtualCardException;
use App\Domain\Cards\Models\VirtualCard;
use App\Domain\Cards\Services\CardFundingService;
use App\Domain\Cards\Services\FxQuoteService;
use App\Domain\Cards\Services\VirtualCardService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\RendersComingSoon;
use App\Http\Controllers\Web\Concerns\VerifiesPin;
use App\Http\Resources\Api\V1\VirtualCardResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CardsController extends Controller
{
    use RendersComingSoon;
    use VerifiesPin;

    public function __construct(
        private readonly VirtualCardService $cards,
        private readonly CardFundingService $funding,
        private readonly FxQuoteService $fx,
        private readonly PinService $pins,
    ) {}

    public function index(Request $request): Response
    {
        if ($soon = $this->comingSoonIfDisabled('cards', [
            'title' => 'Virtual cards',
            'description' => 'NGN and USD cards for online spend are on the way. We’re finishing secure card issuing before this goes live.',
        ])) {
            return $soon;
        }

        /** @var User $user */
        $user = $request->user();
        $cards = $this->cards->forUser($user);

        if ($this->cards->isReady()) {
            $cards = $cards->map(function (VirtualCard $card): VirtualCard {
                try {
                    return $this->cards->syncBalance($card);
                } catch (\Throwable) {
                    return $card;
                }
            });
        }

        $byCurrency = [];
        foreach (config('reton.cards.currencies', ['NGN', 'USD']) as $currency) {
            $match = $cards->firstWhere('currency', $currency);
            $byCurrency[$currency] = $match ? (new VirtualCardResource($match))->resolve() : null;
        }

        return Inertia::render('Cards', [
            'cards' => $byCurrency,
            'cardsReady' => $this->cards->isReady(),
            'cardsDriver' => config('services.bridgecard.driver', 'fake'),
            'fx' => [
                'usd_ngn_rate' => (float) config('reton.fx.usd_ngn_rate', 1600),
                'spread_bps' => (int) config('reton.fx.spread_bps', 150),
            ],
            'minFunding' => config('reton.cards.min_funding_minor', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($denied = $this->denyIfComingSoon(
            'cards',
            'Virtual cards are coming soon. Your balance was not charged.',
        )) {
            return $denied;
        }

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'wallet_id' => ['required', 'uuid', 'exists:wallets,id'],
            'currency' => ['required', Rule::in(config('reton.cards.currencies', ['NGN', 'USD']))],
            'pin' => ['required', 'string'],
        ]);

        $wallet = Wallet::findOrFail($validated['wallet_id']);
        $this->authorize('operate', $wallet);
        $this->verifyPin($this->pins, $user, $validated['pin']);

        $currency = strtoupper((string) $validated['currency']);

        try {
            $this->cards->issue($user, $wallet, $currency);
        } catch (VirtualCardException $e) {
            throw ValidationException::withMessages(['pin' => $e->getMessage()]);
        }

        $label = $currency === 'USD' ? 'USD Mastercard' : 'NGN Mastercard';

        return redirect()->route('cards')->with('success', "Your {$label} virtual card is ready.");
    }

    public function fund(Request $request, VirtualCard $card): RedirectResponse
    {
        if ($denied = $this->denyIfComingSoon(
            'cards',
            'Virtual cards are coming soon. Your balance was not charged.',
        )) {
            return $denied;
        }

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'wallet_id' => ['required', 'uuid', 'exists:wallets,id'],
            'amount_minor' => ['required', 'integer', 'min:100'],
            'pin' => ['required', 'string'],
        ]);

        $this->authorize('operate', $card);
        $this->verifyPin($this->pins, $user, $validated['pin']);

        $wallet = Wallet::findOrFail($validated['wallet_id']);
        $this->authorize('operate', $wallet);

        try {
            $this->funding->fund($card, $wallet, (int) $validated['amount_minor']);
            $this->cards->syncBalance($card);
        } catch (VirtualCardException $e) {
            throw ValidationException::withMessages(['pin' => $e->getMessage()]);
        }

        return back()->with('success', 'Card funding is processing — balance updates shortly.');
    }

    public function quote(Request $request): JsonResponse
    {
        if (! (bool) config('reton.features.cards', false)) {
            abort(503, 'Virtual cards are coming soon.');
        }

        $validated = $request->validate([
            'source_currency' => ['required', 'string', 'size:3'],
            'target_currency' => ['required', 'string', 'size:3'],
            'target_amount_minor' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $quote = $this->fx->quote(
                (string) $validated['source_currency'],
                (string) $validated['target_currency'],
                (int) $validated['target_amount_minor'],
            );
        } catch (VirtualCardException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($quote->toArray());
    }

    public function reveal(Request $request): JsonResponse
    {
        if (! (bool) config('reton.features.cards', false)) {
            abort(503, 'Virtual cards are coming soon.');
        }

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'pin' => ['required', 'string'],
            'currency' => ['nullable', Rule::in(config('reton.cards.currencies', ['NGN', 'USD']))],
        ]);

        $this->verifyPin($this->pins, $user, $validated['pin']);

        $currency = strtoupper((string) ($validated['currency'] ?? 'NGN'));
        $card = $this->cards->forUserAndCurrency($user, $currency);

        if ($card === null) {
            abort(404);
        }

        $this->authorize('view', $card);

        return response()->json($this->cards->reveal($card));
    }

    public function freeze(Request $request): RedirectResponse
    {
        return $this->toggleFreeze($request, true);
    }

    public function unfreeze(Request $request): RedirectResponse
    {
        return $this->toggleFreeze($request, false);
    }

    private function toggleFreeze(Request $request, bool $freeze): RedirectResponse
    {
        if ($denied = $this->denyIfComingSoon(
            'cards',
            'Virtual cards are coming soon.',
        )) {
            return $denied;
        }

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'pin' => ['required', 'string'],
            'currency' => ['nullable', Rule::in(config('reton.cards.currencies', ['NGN', 'USD']))],
        ]);

        $this->verifyPin($this->pins, $user, $validated['pin']);

        $currency = strtoupper((string) ($validated['currency'] ?? 'NGN'));
        $card = $this->cards->forUserAndCurrency($user, $currency);

        if ($card === null) {
            abort(404);
        }

        $this->authorize('operate', $card);

        try {
            $freeze ? $this->cards->freeze($card) : $this->cards->unfreeze($card);
        } catch (VirtualCardException $e) {
            throw ValidationException::withMessages(['pin' => $e->getMessage()]);
        }

        return back()->with('success', $freeze ? 'Card frozen.' : 'Card unfrozen.');
    }
}
