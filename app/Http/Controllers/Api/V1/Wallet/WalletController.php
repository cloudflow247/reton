<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Domain\Auth\Services\PinService;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Exceptions\FraudBlockedException;
use App\Domain\Fraud\Services\FraudService;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use App\Domain\Wallet\Support\RetonId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Wallet\TransferRequest;
use App\Http\Requests\Api\V1\Wallet\WithdrawRequest;
use App\Http\Resources\Api\V1\StatementEntryResource;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Http\Resources\Api\V1\WalletResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly PinService $pins,
        private readonly FraudService $fraud,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            WalletResource::collection($user->wallets()->get()),
            'Wallets retrieved.',
        );
    }

    public function show(Request $request, Wallet $wallet): JsonResponse
    {
        $this->authorize('view', $wallet);

        return ApiResponse::success(new WalletResource($wallet));
    }

    /**
     * Resolve a RETON ID to a wallet and its holder's name, so a sender can
     * confirm the recipient before paying - like a bank name enquiry.
     */
    public function lookup(Request $request): JsonResponse
    {
        $number = RetonId::normalize(
            is_string($request->query('account_number'))
                ? $request->query('account_number')
                : null,
        );

        if ($number === null || ! RetonId::isValid($number)) {
            throw ValidationException::withMessages([
                'account_number' => ['Enter a valid RETON ID (R + 9 digits).'],
            ]);
        }

        $wallet = Wallet::where('account_number', $number)->first();

        if (! $wallet instanceof Wallet) {
            abort(404);
        }

        $name = 'Reton user';
        if ($wallet->owner_type === User::class) {
            $user = User::find($wallet->owner_id);
            if ($user instanceof User) {
                $name = (string) $user->name;
            }
        }

        return ApiResponse::success([
            'wallet_id' => $wallet->id,
            'account_number' => $wallet->account_number,
            'account_name' => $name,
        ]);
    }

    public function transfer(TransferRequest $request, Wallet $wallet): JsonResponse
    {
        $this->authorize('operate', $wallet);

        $recipient = Wallet::findOrFail($request->string('to_wallet_id')->toString());

        if ($recipient->is($wallet)) {
            throw ValidationException::withMessages([
                'to_wallet_id' => ['You cannot transfer to the same wallet.'],
            ]);
        }

        $this->verifyPin($request);

        $amount = Money::of($request->integer('amount'), $wallet->currency);
        $this->fraudGate($request, $wallet, $amount, 'transfer', $recipient);

        $transaction = $this->wallets->transfer(
            $wallet,
            $recipient,
            $amount,
            $this->idempotencyKey($request),
        );

        return ApiResponse::success(new TransactionResource($transaction), 'Transfer completed.');
    }

    public function withdraw(WithdrawRequest $request, Wallet $wallet): JsonResponse
    {
        $this->authorize('operate', $wallet);
        $this->verifyPin($request);

        $amount = Money::of($request->integer('amount'), $wallet->currency);
        $this->fraudGate($request, $wallet, $amount, 'withdrawal', null);

        $transaction = $this->wallets->withdraw(
            $wallet,
            $amount,
            $this->idempotencyKey($request),
        );

        return ApiResponse::success(new TransactionResource($transaction), 'Withdrawal initiated.');
    }

    public function transactions(Request $request, Wallet $wallet): JsonResponse
    {
        $this->authorize('view', $wallet);

        $entries = LedgerEntry::where('ledger_account_id', $wallet->ledger_account_id)
            ->with('transaction')
            ->latest('created_at')
            ->paginate(20);

        return ApiResponse::paginated($entries, StatementEntryResource::collection($entries));
    }

    private function verifyPin(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();

        $this->pins->verify($user, $request->string('pin')->toString());
    }

    private function fraudGate(Request $request, Wallet $wallet, Money $amount, string $action, ?Wallet $beneficiary): void
    {
        /** @var User $user */
        $user = $request->user();

        $assessment = $this->fraud->evaluate(new FraudContext(
            user: $user,
            wallet: $wallet,
            amount: $amount,
            action: $action,
            beneficiary: $beneficiary,
            deviceFingerprint: $request->header('X-Device-Fingerprint'),
            ipAddress: $request->ip(),
        ));

        if ($assessment->isBlocked()) {
            throw FraudBlockedException::make();
        }
    }

    private function idempotencyKey(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
