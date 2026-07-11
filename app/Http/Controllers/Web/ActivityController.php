<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Support\StatementMoneyFlow;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StatementEntryResource;
use App\Http\Resources\Api\V1\TransferResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ActivityController extends Controller
{
    private const STATEMENT_LIMIT = 50;

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $walletIds = $user->wallets()->pluck('id')->all();
        $wallet = $user->wallets()->first();

        $transfers = Transfer::query()
            ->where(function ($query) use ($walletIds): void {
                $query->whereIn('sender_wallet_id', $walletIds)
                    ->orWhereIn('receiver_wallet_id', $walletIds);
            })
            ->with('hold')
            ->latest()
            ->limit(self::STATEMENT_LIMIT)
            ->get();

        $statement = $wallet instanceof Wallet
            ? LedgerEntry::where('ledger_account_id', $wallet->ledger_account_id)
                ->with('transaction')
                ->latest('created_at')
                ->limit(self::STATEMENT_LIMIT)
                ->get()
            : collect();

        $flow = StatementMoneyFlow::fromEntries($statement);

        return Inertia::render('Activity', [
            'transfers' => TransferResource::collection($transfers),
            'statement' => StatementEntryResource::collection($statement),
            'flow' => $flow,
            'windowLabel' => 'Last '.self::STATEMENT_LIMIT.' movements',
        ]);
    }

    public function show(Request $request, string $entry): Response
    {
        /** @var User $user */
        $user = $request->user();
        $walletIds = $user->wallets()->pluck('ledger_account_id')->filter()->all();

        $ledgerEntry = LedgerEntry::query()
            ->with('transaction')
            ->whereKey($entry)
            ->first();

        if (! $ledgerEntry instanceof LedgerEntry || ! in_array($ledgerEntry->ledger_account_id, $walletIds, true)) {
            throw new NotFoundHttpException('Transaction not found.');
        }

        $wallet = Wallet::query()
            ->where('ledger_account_id', $ledgerEntry->ledger_account_id)
            ->with(['staticAccount' => fn ($q) => $q->where('status', StaticAccountStatus::Active)])
            ->first();

        $transfer = null;
        $reference = $ledgerEntry->transaction?->reference;

        if (is_string($reference) && $reference !== '' && $wallet instanceof Wallet) {
            $transfer = Transfer::query()
                ->where('reference', $reference)
                ->where(function ($query) use ($wallet): void {
                    $query->where('sender_wallet_id', $wallet->id)
                        ->orWhere('receiver_wallet_id', $wallet->id);
                })
                ->with('hold')
                ->first();
        }

        return Inertia::render('Activity/Show', [
            'entry' => (new StatementEntryResource($ledgerEntry))->resolve(),
            'transfer' => $transfer instanceof Transfer
                ? (new TransferResource($transfer))->resolve()
                : null,
            'wallet' => $wallet instanceof Wallet
                ? [
                    'id' => $wallet->id,
                    'account_number' => $wallet->account_number,
                    'currency' => $wallet->currency,
                    'available_balance' => $wallet->availableMinor(),
                    'held_balance' => $wallet->heldMinor(),
                    'balance' => $wallet->ledgerMinor(),
                ]
                : null,
            'receipt' => [
                'issued_at' => now()->toIso8601String(),
                'app' => config('app.name', 'Reton'),
                'user_name' => $user->name,
                'user_email' => $user->email,
            ],
        ]);
    }
}
