<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Services\AlatpayDepositService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\InitiateDepositRequest;
use App\Http\Resources\Api\V1\DepositResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function __construct(private readonly AlatpayDepositService $deposits) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deposits = Deposit::where('user_id', $user->getKey())->latest()->paginate(20);

        return ApiResponse::paginated($deposits, DepositResource::collection($deposits));
    }

    public function store(InitiateDepositRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wallet = Wallet::findOrFail($request->string('wallet_id')->toString());
        $this->authorize('operate', $wallet);

        $deposit = $this->deposits->initiate(
            $user,
            $wallet,
            Money::of($request->integer('amount'), $wallet->currency),
        );

        return ApiResponse::created(new DepositResource($deposit), 'Deposit initiated.');
    }

    public function show(Request $request, Deposit $deposit): JsonResponse
    {
        $this->authorize('view', $deposit);

        return ApiResponse::success(new DepositResource($deposit));
    }
}
