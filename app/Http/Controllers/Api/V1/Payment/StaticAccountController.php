<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\ProvisionStaticAccountRequest;
use App\Http\Requests\Api\V1\Payment\VerifyStaticAccountRequest;
use App\Http\Resources\Api\V1\StaticAccountResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaticAccountController extends Controller
{
    public function __construct(private readonly StaticAccountService $accounts) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $accounts = StaticAccount::where('user_id', $user->getKey())->latest()->paginate(20);

        return ApiResponse::paginated($accounts, StaticAccountResource::collection($accounts));
    }

    public function store(ProvisionStaticAccountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wallet = Wallet::findOrFail($request->string('wallet_id')->toString());
        $this->authorize('operate', $wallet);

        $account = $this->accounts->provision(
            $user,
            $wallet,
            StaticWalletType::from($request->string('wallet_type')->toString()),
            $request->input('bvn'),
        );

        return ApiResponse::created(new StaticAccountResource($account), 'Static account provisioning started.');
    }

    public function show(Request $request, StaticAccount $staticAccount): JsonResponse
    {
        $this->authorize('view', $staticAccount);

        return ApiResponse::success(new StaticAccountResource($staticAccount));
    }

    public function verify(VerifyStaticAccountRequest $request, StaticAccount $staticAccount): JsonResponse
    {
        $this->authorize('view', $staticAccount);

        $account = $this->accounts->verify($staticAccount, $request->string('otp')->toString());

        return ApiResponse::success(new StaticAccountResource($account), 'Static account activated.');
    }
}
