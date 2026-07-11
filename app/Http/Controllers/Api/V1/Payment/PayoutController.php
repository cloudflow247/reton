<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Auth\Services\PinService;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Exceptions\FraudBlockedException;
use App\Domain\Fraud\Services\FraudService;
use App\Domain\Payments\Models\Payout;
use App\Domain\Payments\Services\PayoutService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\RequestPayoutRequest;
use App\Http\Resources\Api\V1\PayoutResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function __construct(
        private readonly PayoutService $payouts,
        private readonly PinService $pins,
        private readonly FraudService $fraud,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payouts = Payout::where('user_id', $user->getKey())->latest()->paginate(20);

        return ApiResponse::paginated($payouts, PayoutResource::collection($payouts));
    }

    public function store(RequestPayoutRequest $request): JsonResponse
    {
        if (! (bool) config('reton.features.withdraw', true)) {
            return ApiResponse::error(
                'Bank withdrawals are coming soon. Your balance was not charged.',
                'feature_disabled',
                503,
            );
        }

        /** @var User $user */
        $user = $request->user();

        $wallet = Wallet::findOrFail($request->string('wallet_id')->toString());
        $this->authorize('operate', $wallet);

        $this->pins->verify($user, $request->string('pin')->toString());

        $amount = Money::of($request->integer('amount'), $wallet->currency);

        $assessment = $this->fraud->evaluate(new FraudContext(
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

        $payout = $this->payouts->request(
            $user,
            $wallet,
            $amount,
            $request->string('bank_code')->toString(),
            $request->string('account_number')->toString(),
            $request->string('account_name')->toString(),
        );

        return ApiResponse::created(new PayoutResource($payout), 'Payout initiated.');
    }

    public function show(Request $request, Payout $payout): JsonResponse
    {
        $this->authorize('view', $payout);

        return ApiResponse::success(new PayoutResource($payout));
    }
}
