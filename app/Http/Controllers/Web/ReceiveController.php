<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Kyc\Services\KycService;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\KycResource;
use App\Http\Resources\Api\V1\StaticAccountResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiveController extends Controller
{
    public function __construct(
        private readonly KycService $kyc,
        private readonly StaticAccountService $staticAccounts,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $wallet = $user->wallets()->first();
        $profile = $this->kyc->forUser($user);

        $staticAccount = $this->staticAccounts->activeFundingAccountFor(
            $user,
            $wallet instanceof Wallet ? $wallet : null,
        );

        if ($staticAccount === null && $wallet instanceof Wallet) {
            $staticAccount = StaticAccount::query()
                ->where('wallet_id', $wallet->getKey())
                ->latest()
                ->first()
                ?? StaticAccount::query()
                    ->where('user_id', $user->getKey())
                    ->latest()
                    ->first();
        }

        return Inertia::render('Receive', [
            'kyc' => (new KycResource($profile))->resolve(),
            'staticAccount' => $staticAccount ? (new StaticAccountResource($staticAccount))->resolve() : null,
        ]);
    }

    public function provision(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'wallet_id' => ['required', 'uuid', 'exists:wallets,id'],
        ]);

        $wallet = Wallet::findOrFail($validated['wallet_id']);
        $this->authorize('operate', $wallet);

        $account = $this->staticAccounts->provisionForWallet($user, $wallet);

        $message = $account->isActive()
            ? 'Your permanent deposit account is ready.'
            : 'We sent an OTP - enter it to activate your deposit account.';

        return back()->with('success', $message);
    }

    public function verify(Request $request, StaticAccount $staticAccount): RedirectResponse
    {
        $this->authorize('view', $staticAccount);

        $validated = $request->validate([
            'otp' => ['required', 'string', 'min:4', 'max:8'],
        ]);

        try {
            $this->staticAccounts->verify($staticAccount, $validated['otp']);
        } catch (\App\Domain\Payments\Alatpay\Exceptions\AlatpayException $e) {
            return back()->withErrors([
                'otp' => $e->userFacingMessage('Invalid or expired code. Try again.'),
            ]);
        }

        return back()->with('success', 'Deposit account activated - share the number to receive bank transfers.');
    }
}
