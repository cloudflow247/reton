<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Recovery;

use App\Domain\Auth\Services\PinService;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Recovery\Services\RecoveryService;
use App\Domain\Transfers\Models\Transfer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Recovery\AddRecoveryEvidenceRequest;
use App\Http\Requests\Api\V1\Recovery\DisputeRecoveryRequest;
use App\Http\Requests\Api\V1\Recovery\ReportRecoveryRequest;
use App\Http\Requests\Api\V1\Recovery\ReturnRecoveryRequest;
use App\Http\Resources\Api\V1\RecoveryResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecoveryController extends Controller
{
    public function __construct(
        private readonly RecoveryService $recoveries,
        private readonly PinService $pins,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $walletIds = $user->wallets()->pluck('id')->all();

        $recoveries = Recovery::query()
            ->where(function ($query) use ($walletIds): void {
                $query->whereIn('sender_wallet_id', $walletIds)
                    ->orWhereIn('receiver_wallet_id', $walletIds);
            })
            ->latest()
            ->paginate(20);

        return ApiResponse::paginated($recoveries, RecoveryResource::collection($recoveries));
    }

    public function store(ReportRecoveryRequest $request, Transfer $transfer): JsonResponse
    {
        $this->authorize('recover', $transfer);

        /** @var User $user */
        $user = $request->user();
        $this->pins->verify($user, $request->string('pin')->toString());

        $recovery = $this->recoveries->report($transfer, $user, $request->string('reason')->toString());

        return ApiResponse::created(new RecoveryResource($recovery->load('events')), 'Recovery reported.');
    }

    public function show(Request $request, Recovery $recovery): JsonResponse
    {
        $this->authorize('view', $recovery);

        return ApiResponse::success(new RecoveryResource($recovery->load('events')));
    }

    public function return(ReturnRecoveryRequest $request, Recovery $recovery): JsonResponse
    {
        $this->authorize('respond', $recovery);

        /** @var User $user */
        $user = $request->user();
        $this->pins->verify($user, $request->string('pin')->toString());

        $returned = $this->recoveries->returnToSender($recovery, $user);

        return ApiResponse::success(new RecoveryResource($returned->load('events')), 'Funds returned.');
    }

    public function dispute(DisputeRecoveryRequest $request, Recovery $recovery): JsonResponse
    {
        $this->authorize('respond', $recovery);

        /** @var User $user */
        $user = $request->user();
        $reason = $request->filled('reason') ? $request->string('reason')->toString() : null;

        $disputed = $this->recoveries->dispute($recovery, $user, $reason);

        return ApiResponse::success(new RecoveryResource($disputed->load('events')), 'Recovery disputed.');
    }

    public function evidence(AddRecoveryEvidenceRequest $request, Recovery $recovery): JsonResponse
    {
        $this->authorize('contribute', $recovery);

        /** @var User $user */
        $user = $request->user();

        $metadata = $request->filled('url') ? ['url' => $request->string('url')->toString()] : [];
        $this->recoveries->addEvidence($recovery, $user, $request->string('note')->toString(), $metadata);

        return ApiResponse::success(new RecoveryResource($recovery->fresh()?->load('events')), 'Evidence recorded.');
    }
}
