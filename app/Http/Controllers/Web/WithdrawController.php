<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Auth\Services\PinService;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Exceptions\FraudBlockedException;
use App\Domain\Fraud\Services\FraudService;
use App\Domain\Kyc\Services\KycLimitService;
use App\Domain\Payments\Contracts\PayoutGateway;
use App\Domain\Payments\Models\Payout;
use App\Domain\Payments\Services\PayoutService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\RendersComingSoon;
use App\Http\Controllers\Web\Concerns\VerifiesPin;
use App\Http\Resources\Api\V1\PayoutResource;
use App\Models\User;
use App\Support\Banking\AccountNameMatcher;
use App\Support\Banking\NigerianBanks;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WithdrawController extends Controller
{
    use RendersComingSoon;
    use VerifiesPin;

    public function index(Request $request, PayoutGateway $payouts): Response
    {
        if ($soon = $this->comingSoonIfDisabled('withdraw', [
            'title' => 'Withdraw to bank',
            'description' => 'Cash out to your own Nigerian bank account is almost ready. Your balance stays safe — send money and Callback Protection work today.',
        ])) {
            return $soon;
        }

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Withdraw', [
            'banks' => NigerianBanks::all(),
            'accountNameHint' => strtoupper((string) $user->name),
            'recentPayouts' => $this->recentPayoutsFor($user),
            'payoutsAvailable' => $payouts->supportsOutboundTransfers(),
        ]);
    }

    public function store(
        Request $request,
        PayoutService $payouts,
        PinService $pins,
        FraudService $fraud,
        KycLimitService $kycLimits,
    ): RedirectResponse {
        if ($denied = $this->denyIfComingSoon(
            'withdraw',
            'Bank withdrawals are coming soon. Your balance was not charged.',
        )) {
            return $denied;
        }

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'wallet_id' => ['required', 'uuid', 'exists:wallets,id'],
            'amount' => ['required', 'integer', 'min:10000'],
            'bank_code' => ['required', 'string', 'max:16'],
            'account_number' => ['required', 'string', 'regex:/^\d{10}$/'],
            'account_name' => ['required', 'string', 'max:120'],
            'pin' => ['required', 'string'],
        ]);

        $wallet = Wallet::findOrFail($validated['wallet_id']);
        $this->authorize('operate', $wallet);
        $this->verifyPin($pins, $user, $validated['pin']);

        $accountName = strtoupper(trim($validated['account_name']));

        if (! AccountNameMatcher::matches($accountName, (string) $user->name)) {
            throw ValidationException::withMessages([
                'account_name' => ['Bank account name must match your Reton profile name ('.$user->name.').'],
            ]);
        }

        $amount = Money::of($validated['amount'], $wallet->currency);
        $kycLimits->assertCanSpend($user, $wallet, $amount);

        $assessment = $fraud->evaluate(new FraudContext(
            user: $user,
            wallet: $wallet,
            amount: $amount,
            action: 'payout',
            deviceFingerprint: $request->header('X-Device-Fingerprint'),
            ipAddress: $request->ip(),
        ));

        if ($assessment->isBlocked()) {
            throw FraudBlockedException::make();
        }

        $payout = $payouts->request(
            $user,
            $wallet,
            $amount,
            $validated['bank_code'],
            $validated['account_number'],
            $accountName,
        );

        return redirect()->route('withdraw')->with('payout', (new PayoutResource($payout))->resolve());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentPayoutsFor(User $user): array
    {
        try {
            $recent = Payout::query()
                ->where('user_id', $user->getKey())
                ->latest()
                ->limit(5)
                ->get();

            /** @var list<array<string, mixed>> $resolved */
            $resolved = PayoutResource::collection($recent)->resolve();

            return $resolved;
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }
}
