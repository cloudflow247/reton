<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Services\PaymentRequestService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\CreatePaymentRequestRequest;
use App\Http\Resources\Api\V1\PaymentRequestResource;
use App\Http\Resources\Api\V1\PublicPaymentRequestResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentRequestController extends Controller
{
    public function __construct(private readonly PaymentRequestService $requests) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $requests = PaymentRequest::where('requester_user_id', $user->getKey())->latest()->paginate(20);

        return ApiResponse::paginated($requests, PaymentRequestResource::collection($requests));
    }

    public function store(CreatePaymentRequestRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wallet = Wallet::findOrFail($request->string('wallet_id')->toString());
        $this->authorize('operate', $wallet);

        $paymentRequest = $this->requests->create(
            $user,
            $wallet,
            Money::of($request->integer('amount'), $wallet->currency),
            $request->string('title')->toString(),
            $request->input('description'),
        );

        return ApiResponse::created(new PaymentRequestResource($paymentRequest), 'Payment request created.');
    }

    public function show(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('view', $paymentRequest);

        return ApiResponse::success(new PaymentRequestResource($paymentRequest));
    }

    public function cancel(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('cancel', $paymentRequest);

        $paymentRequest = $this->requests->cancel($paymentRequest);

        return ApiResponse::success(new PaymentRequestResource($paymentRequest), 'Payment request cancelled.');
    }

    public function publicShow(string $reference): JsonResponse
    {
        $paymentRequest = PaymentRequest::where('reference', $reference)->firstOrFail();

        return ApiResponse::success(new PublicPaymentRequestResource($paymentRequest));
    }
}
