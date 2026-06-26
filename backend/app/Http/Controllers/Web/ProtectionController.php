<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Auth\Services\PinService;
use App\Domain\Callback\Models\Callback;
use App\Domain\Callback\Services\CallbackService;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Recovery\Services\RecoveryService;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Services\TransferService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\VerifiesPin;
use App\Http\Requests\Api\V1\Callback\AcceptCallbackRequest;
use App\Http\Requests\Api\V1\Callback\InitiateCallbackRequest;
use App\Http\Requests\Api\V1\Callback\RejectCallbackRequest;
use App\Http\Requests\Api\V1\Recovery\DisputeRecoveryRequest;
use App\Http\Requests\Api\V1\Recovery\ReportRecoveryRequest;
use App\Http\Requests\Api\V1\Recovery\ReturnRecoveryRequest;
use App\Http\Requests\Api\V1\Transfer\ReleaseTransferRequest;
use App\Http\Resources\Api\V1\CallbackResource;
use App\Http\Resources\Api\V1\RecoveryResource;
use App\Http\Resources\Api\V1\TransferResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProtectionController extends Controller
{
    use VerifiesPin;

    public function __construct(
        private readonly TransferService $transfers,
        private readonly CallbackService $callbacks,
        private readonly RecoveryService $recoveries,
        private readonly PinService $pins,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $walletIds = $user->wallets()->pluck('id')->all();
        $walletId = $user->wallets()->value('id');

        $transfers = Transfer::query()
            ->where(fn ($q) => $q->whereIn('sender_wallet_id', $walletIds)->orWhereIn('receiver_wallet_id', $walletIds))
            ->with('hold')
            ->latest()
            ->get();

        $callbacks = Callback::query()
            ->whereHas('transfer', fn ($q) => $q->whereIn('sender_wallet_id', $walletIds)->orWhereIn('receiver_wallet_id', $walletIds))
            ->with('events')
            ->latest()
            ->get();

        $recoveries = Recovery::query()
            ->where(fn ($q) => $q->whereIn('sender_wallet_id', $walletIds)->orWhereIn('receiver_wallet_id', $walletIds))
            ->with('events')
            ->latest()
            ->get();

        return Inertia::render('Protection', [
            'walletId' => $walletId,
            'transfers' => TransferResource::collection($transfers),
            'callbacks' => CallbackResource::collection($callbacks),
            'recoveries' => RecoveryResource::collection($recoveries),
        ]);
    }

    public function release(ReleaseTransferRequest $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('release', $transfer);

        /** @var User $user */
        $user = $request->user();
        $this->verifyPin($this->pins, $user, $request->string('pin')->toString());

        $this->transfers->release($transfer);

        return back()->with('success', 'Transfer released.');
    }

    public function storeCallback(InitiateCallbackRequest $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('callback', $transfer);

        /** @var User $user */
        $user = $request->user();
        $this->verifyPin($this->pins, $user, $request->string('pin')->toString());

        $this->callbacks->initiate($transfer, $user, $request->string('reason')->toString());

        return back()->with('success', 'Callback raised.');
    }

    public function acceptCallback(AcceptCallbackRequest $request, Callback $callback): RedirectResponse
    {
        $this->authorize('respond', $callback);

        /** @var User $user */
        $user = $request->user();
        $this->verifyPin($this->pins, $user, $request->string('pin')->toString());

        $this->callbacks->accept($callback, $user);

        return back()->with('success', 'Callback accepted — funds returned.');
    }

    public function rejectCallback(RejectCallbackRequest $request, Callback $callback): RedirectResponse
    {
        $this->authorize('respond', $callback);

        /** @var User $user */
        $user = $request->user();
        $reason = $request->filled('reason') ? $request->string('reason')->toString() : null;

        $this->callbacks->reject($callback, $user, $reason);

        return back()->with('success', 'Callback rejected — sent for review.');
    }

    public function storeRecovery(ReportRecoveryRequest $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('recover', $transfer);

        /** @var User $user */
        $user = $request->user();
        $this->verifyPin($this->pins, $user, $request->string('pin')->toString());

        $this->recoveries->report($transfer, $user, $request->string('reason')->toString());

        return back()->with('success', 'Wrong transfer reported.');
    }

    public function returnRecovery(ReturnRecoveryRequest $request, Recovery $recovery): RedirectResponse
    {
        $this->authorize('respond', $recovery);

        /** @var User $user */
        $user = $request->user();
        $this->verifyPin($this->pins, $user, $request->string('pin')->toString());

        $this->recoveries->returnToSender($recovery, $user);

        return back()->with('success', 'Funds returned to sender.');
    }

    public function disputeRecovery(DisputeRecoveryRequest $request, Recovery $recovery): RedirectResponse
    {
        $this->authorize('respond', $recovery);

        /** @var User $user */
        $user = $request->user();
        $reason = $request->filled('reason') ? $request->string('reason')->toString() : null;

        $this->recoveries->dispute($recovery, $user, $reason);

        return back()->with('success', 'Recovery disputed — sent for review.');
    }
}
