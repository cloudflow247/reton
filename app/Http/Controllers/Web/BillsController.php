<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Auth\Services\PinService;
use App\Domain\Bills\Enums\BillCategory;
use App\Domain\Bills\Models\BillPayment;
use App\Domain\Bills\Remita\Exceptions\BillProviderException;
use App\Domain\Bills\Services\BillPaymentService;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Exceptions\FraudBlockedException;
use App\Domain\Fraud\Services\FraudService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\VerifiesPin;
use App\Http\Requests\Api\V1\Bill\PayBillRequest;
use App\Http\Resources\Api\V1\BillPaymentResource;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BillsController extends Controller
{
    use VerifiesPin;

    public function __construct(
        private readonly BillPaymentService $bills,
        private readonly PinService $pins,
        private readonly FraudService $fraud,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $recent = BillPayment::where('user_id', $user->getKey())
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('Bills', [
            'billsProvider' => config('reton.bills.provider', 'interswitch'),
            'rrrEnabled' => $this->bills->rrrEnabled(),
            'categories' => collect(BillCategory::cases())
                ->reject(fn (BillCategory $c) => $c === BillCategory::Rrr && ! $this->bills->rrrEnabled())
                ->map(fn (BillCategory $c): array => [
                'value' => $c->value,
                'label' => $c->displayName(),
                'fixed_amount' => $c->hasFixedAmount(),
            ])->values()->all(),
            'bills' => BillPaymentResource::collection($recent),
        ]);
    }

    /**
     * RRR name enquiry: resolve a Remita Retrieval Reference to its biller and
     * amount. A small JSON endpoint (not Inertia) because it runs as a lookup.
     */
    public function lookup(Request $request): JsonResponse
    {
        $rrr = $request->query('rrr');

        if (! is_string($rrr) || preg_match('/^\d{12}$/', $rrr) !== 1) {
            throw ValidationException::withMessages([
                'rrr' => ['Enter a valid 12-digit Remita Retrieval Reference.'],
            ]);
        }

        try {
            $inquiry = $this->bills->lookupRrr($rrr);
        } catch (BillProviderException) {
            abort(404);
        }

        return response()->json([
            'rrr' => $inquiry->rrr,
            'biller_name' => $inquiry->billerName,
            'amount' => $inquiry->amount->amount,
            'currency' => $inquiry->amount->currency,
            'payer_name' => $inquiry->payerName,
            'is_paid' => $inquiry->isPaid,
        ]);
    }

    public function store(PayBillRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wallet = Wallet::findOrFail($request->string('wallet_id')->toString());
        $this->authorize('operate', $wallet);

        $this->verifyPin($this->pins, $user, $request->string('pin')->toString());

        $category = BillCategory::from($request->string('category')->toString());

        // For an RRR the biller and amount are authoritative from the lookup —
        // never trust a client-supplied amount for a fixed-amount bill.
        if ($category->hasFixedAmount()) {
            $reference = $request->string('customer_reference')->toString();

            try {
                $inquiry = $this->bills->lookupRrr($reference);
            } catch (BillProviderException $e) {
                throw ValidationException::withMessages(['customer_reference' => $e->getMessage()]);
            }

            $amount = $inquiry->amount;
            $billerName = $inquiry->billerName;
        } else {
            $amount = Money::of($request->integer('amount'), $wallet->currency);
            $billerName = $request->string('biller_name')->toString();
        }

        $assessment = $this->fraud->evaluate(new FraudContext(
            user: $user,
            wallet: $wallet,
            amount: $amount,
            action: 'bill_payment',
            deviceFingerprint: $request->header('X-Device-Fingerprint'),
            ipAddress: $request->ip(),
        ));

        if ($assessment->isBlocked()) {
            throw FraudBlockedException::make();
        }

        $bill = $this->bills->pay(
            $user,
            $wallet,
            $category,
            $request->string('biller_code')->toString(),
            $billerName,
            $request->string('customer_reference')->toString(),
            $amount,
            $request->input('payment_code') ? $request->string('payment_code')->toString() : null,
        );

        // Flash a receipt the Bills page shows as its outcome screen.
        return back()->with('bill', (new BillPaymentResource($bill))->resolve());
    }
}
