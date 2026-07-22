<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Transfer;

use App\Domain\Auth\Services\PinService;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Exceptions\FraudBlockedException;
use App\Domain\Fraud\Services\FraudService;
use App\Domain\Transfers\Enums\TransferType;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transfer\CreateTransferRequest;
use App\Http\Requests\Api\V1\Transfer\ReleaseTransferRequest;
use App\Http\Resources\Api\V1\TransferResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use App\Support\Http\IdempotencyKey;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transfers,
        private readonly PinService $pins,
        private readonly FraudService $fraud,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $walletIds = $user->wallets()->pluck('id')->all();

        $transfers = Transfer::query()
            ->where(function ($query) use ($walletIds): void {
                $query->whereIn('sender_wallet_id', $walletIds)
                    ->orWhereIn('receiver_wallet_id', $walletIds);
            })
            ->with(['hold', 'senderWallet.owner', 'receiverWallet.owner'])
            ->latest()
            ->paginate(20);

        return ApiResponse::paginated($transfers, TransferResource::collection($transfers));
    }

    public function store(CreateTransferRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $from = Wallet::findOrFail($request->string('from_wallet_id')->toString());
        $this->authorize('operate', $from);

        $to = Wallet::findOrFail($request->string('to_wallet_id')->toString());

        if ($from->currency !== $to->currency) {
            throw ValidationException::withMessages([
                'to_wallet_id' => ['The recipient wallet currency must match the source wallet.'],
            ]);
        }

        $this->pins->verify($user, $request->string('pin')->toString());

        $amount = Money::of($request->integer('amount'), $from->currency);

        // Synchronous fraud gate: high-risk movements are stopped before any
        // ledger posting; flagged-but-allowed ones are recorded as alerts.
        $assessment = $this->fraud->evaluate(new FraudContext(
            user: $user,
            wallet: $from,
            amount: $amount,
            action: 'transfer',
            beneficiary: $to,
            deviceFingerprint: $request->header('X-Device-Fingerprint'),
            ipAddress: $request->ip(),
        ));

        if ($assessment->isBlocked()) {
            throw FraudBlockedException::make();
        }

        $note = $request->filled('note') ? $request->string('note')->toString() : null;
        $key = IdempotencyKey::from($request);

        $transfer = TransferType::from($request->string('type')->toString()) === TransferType::Protected
            ? $this->transfers->sendProtected($user, $from, $to, $amount, $note, $key)
            : $this->transfers->sendNormal($user, $from, $to, $amount, $note, $key);

        return ApiResponse::created(new TransferResource($transfer->load(['hold', 'senderWallet.owner', 'receiverWallet.owner'])), 'Transfer created.');
    }

    public function show(Request $request, Transfer $transfer): JsonResponse
    {
        $this->authorize('view', $transfer);

        return ApiResponse::success(new TransferResource($transfer->load(['hold', 'senderWallet.owner', 'receiverWallet.owner'])));
    }

    public function release(ReleaseTransferRequest $request, Transfer $transfer): JsonResponse
    {
        $this->authorize('release', $transfer);

        /** @var User $user */
        $user = $request->user();
        $this->pins->verify($user, $request->string('pin')->toString());

        $released = $this->transfers->release($transfer);

        return ApiResponse::success(new TransferResource($released->load(['hold', 'senderWallet.owner', 'receiverWallet.owner'])), 'Transfer released.');
    }
}
