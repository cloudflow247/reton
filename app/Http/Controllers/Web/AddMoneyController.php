<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Kyc\Services\KycService;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\DepositMethod;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Payments\Services\AlatpayDepositService;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\InitiateDepositRequest;
use App\Http\Resources\Api\V1\DepositResource;
use App\Http\Resources\Api\V1\KycResource;
use App\Http\Resources\Api\V1\StaticAccountResource;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AddMoneyController extends Controller
{
    public function __construct(
        private readonly AlatpayDepositService $deposits,
        private readonly KycService $kyc,
        private readonly StaticAccountService $staticAccounts,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $openDeposits = DepositResource::collection(
            $this->deposits->openDepositsFor($user),
        )->resolve();

        $pendingDeposit = null;
        $reference = $request->string('reference')->toString();

        if ($reference !== '') {
            $deposit = $this->deposits->findForUser($user, $reference);

            if ($deposit !== null) {
                $pendingDeposit = (new DepositResource($deposit))->resolve();
            }
        } elseif (! $request->boolean('fresh') && count($openDeposits) === 1) {
            // One open payment — resume automatically so reloads never lose context.
            $pendingDeposit = $openDeposits[0];
        }

        $wallet = $user->wallets()->first();
        $profile = $this->kyc->forUser($user);
        $staticAccount = $wallet
            ? StaticAccount::query()->where('wallet_id', $wallet->getKey())->latest()->first()
            : null;

        // Already BVN-verified but missing a linked VA (e.g. verified before auto-link):
        // recover/provision quietly so Add Money shows the account immediately.
        if (
            $wallet !== null
            && $profile->bvn_verified_at !== null
            && ($staticAccount === null || ! $staticAccount->isActive())
        ) {
            try {
                $staticAccount = $this->staticAccounts->provisionForWallet($user, $wallet);
            } catch (ValidationException) {
                // Page still loads; StaticWalletCard can offer a manual retry.
            }
        }

        // VA deposits settle on ALATPay first; credit Reton on visit so users are
        // not stuck waiting on the minute scheduler (or a missed cron tick).
        if ($staticAccount !== null && $staticAccount->isActive()) {
            $credited = $this->staticAccounts->pollActiveForUser($user);
            $staticAccount->refresh();

            if ($credited > 0) {
                $request->session()->flash(
                    'success',
                    $credited === 1
                        ? 'Deposit received — your balance is updated.'
                        : "{$credited} deposits received — your balance is updated.",
                );
            }
        }

        return Inertia::render('AddMoney', [
            'pendingDeposit' => $pendingDeposit,
            'openDeposits' => $openDeposits,
            'kyc' => (new KycResource($profile))->resolve(),
            'staticAccount' => $staticAccount ? (new StaticAccountResource($staticAccount))->resolve() : null,
            'bvnPendingOtp' => $this->kyc->hasPendingAlatpayBvn($user),
            'bvnOtpHint' => $this->kyc->pendingAlatpayBvnHint($user),
            'bvnProvider' => $this->kyc->bvnProvider(),
            'bvnDemoMode' => $this->kyc->bvnDemoMode(),
        ]);
    }

    public function store(InitiateDepositRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wallet = Wallet::findOrFail($request->string('wallet_id')->toString());
        $this->authorize('operate', $wallet);

        $method = DepositMethod::tryFrom($request->string('method')->toString()) ?? DepositMethod::BankTransfer;

        $deposit = $this->deposits->initiate(
            $user,
            $wallet,
            Money::of($request->integer('amount'), $wallet->currency),
            $method,
        );

        if ($method !== DepositMethod::BankTransfer) {
            return redirect()->route('deposits.pay', $deposit);
        }

        return redirect()->route('add-money', ['reference' => $deposit->reference]);
    }

    public function pay(Request $request, Deposit $deposit): HttpResponse|RedirectResponse|Response
    {
        $this->authorize('view', $deposit);

        if ($deposit->status->value !== 'pending') {
            return redirect()->route('add-money', ['reference' => $deposit->reference]);
        }

        if (config('services.alatpay.driver') === 'fake') {
            return Inertia::render('Deposits/AlatpayDemoCheckout', [
                'deposit' => (new DepositResource($deposit))->resolve(),
                'cardOnly' => ($deposit->metadata['method'] ?? '') === DepositMethod::AlatpayCard->value,
            ]);
        }

        $paymentLinkUrl = $deposit->metadata['payment_link_url'] ?? null;

        if (! is_string($paymentLinkUrl) || $paymentLinkUrl === '') {
            return redirect()->route('add-money', ['reference' => $deposit->reference]);
        }

        if ($request->header('X-Inertia')) {
            return Inertia::location($paymentLinkUrl);
        }

        return redirect()->away($paymentLinkUrl);
    }

    public function simulatePay(Request $request, Deposit $deposit): RedirectResponse
    {
        $this->authorize('view', $deposit);

        if ($deposit->status->value !== 'pending' || config('services.alatpay.driver') !== 'fake') {
            return redirect()->route('add-money', ['reference' => $deposit->reference]);
        }

        $gateway = app(AlatpayGateway::class);

        if ($gateway instanceof FakeAlatpayGateway && $deposit->provider_reference !== null) {
            $gateway->markPaid($deposit->provider_reference, $deposit->amount, $deposit->currency);
            $this->deposits->reconcile($deposit->fresh());
        }

        return redirect()->route('add-money', ['reference' => $deposit->reference]);
    }

    public function returnFromAlatpay(Request $request, string $reference): RedirectResponse
    {
        return redirect()->route('add-money', ['reference' => $reference]);
    }
}
