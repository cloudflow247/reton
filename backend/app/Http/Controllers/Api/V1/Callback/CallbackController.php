<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Callback;

use App\Domain\Auth\Services\PinService;
use App\Domain\Callback\Models\Callback;
use App\Domain\Callback\Services\CallbackService;
use App\Domain\Transfers\Models\Transfer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Callback\AcceptCallbackRequest;
use App\Http\Requests\Api\V1\Callback\AddEvidenceRequest;
use App\Http\Requests\Api\V1\Callback\InitiateCallbackRequest;
use App\Http\Requests\Api\V1\Callback\RejectCallbackRequest;
use App\Http\Resources\Api\V1\CallbackResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallbackController extends Controller
{
    public function __construct(
        private readonly CallbackService $callbacks,
        private readonly PinService $pins,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $walletIds = $user->wallets()->pluck('id')->all();

        $callbacks = Callback::query()
            ->whereHas('transfer', function ($query) use ($walletIds): void {
                $query->whereIn('sender_wallet_id', $walletIds)
                    ->orWhereIn('receiver_wallet_id', $walletIds);
            })
            ->latest()
            ->paginate(20);

        return ApiResponse::paginated($callbacks, CallbackResource::collection($callbacks));
    }

    public function store(InitiateCallbackRequest $request, Transfer $transfer): JsonResponse
    {
        $this->authorize('callback', $transfer);

        /** @var User $user */
        $user = $request->user();
        $this->pins->verify($user, $request->string('pin')->toString());

        $callback = $this->callbacks->initiate($transfer, $user, $request->string('reason')->toString());

        return ApiResponse::created(new CallbackResource($callback->load('events')), 'Callback raised.');
    }

    public function show(Request $request, Callback $callback): JsonResponse
    {
        $this->authorize('view', $callback);

        return ApiResponse::success(new CallbackResource($callback->load('events')));
    }

    public function accept(AcceptCallbackRequest $request, Callback $callback): JsonResponse
    {
        $this->authorize('respond', $callback);

        /** @var User $user */
        $user = $request->user();
        $this->pins->verify($user, $request->string('pin')->toString());

        $resolved = $this->callbacks->accept($callback, $user);

        return ApiResponse::success(new CallbackResource($resolved->load('events')), 'Callback accepted.');
    }

    public function reject(RejectCallbackRequest $request, Callback $callback): JsonResponse
    {
        $this->authorize('respond', $callback);

        /** @var User $user */
        $user = $request->user();
        $reason = $request->filled('reason') ? $request->string('reason')->toString() : null;

        $rejected = $this->callbacks->reject($callback, $user, $reason);

        return ApiResponse::success(new CallbackResource($rejected->load('events')), 'Callback rejected.');
    }

    public function evidence(AddEvidenceRequest $request, Callback $callback): JsonResponse
    {
        $this->authorize('contribute', $callback);

        /** @var User $user */
        $user = $request->user();

        $metadata = $request->filled('url') ? ['url' => $request->string('url')->toString()] : [];
        $this->callbacks->addEvidence($callback, $user, $request->string('note')->toString(), $metadata);

        return ApiResponse::success(new CallbackResource($callback->fresh()?->load('events')), 'Evidence recorded.');
    }
}
