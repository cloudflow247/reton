<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\ReceiptPartiesResolver;
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

    public function __construct(
        private readonly ReceiptPartiesResolver $receiptParties,
    ) {}

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
        $walletIds = $user->wallets()->pluck('ledger_account_id')->filter()->values()->all();

        $ledgerEntry = LedgerEntry::query()
            ->with('transaction')
            ->whereKey($entry)
            ->first();

        if (! $ledgerEntry instanceof LedgerEntry || ! in_array((string) $ledgerEntry->ledger_account_id, array_map('strval', $walletIds), true)) {
            throw new NotFoundHttpException('Transaction not found.');
        }

        $wallet = Wallet::query()
            ->where('ledger_account_id', $ledgerEntry->ledger_account_id)
            ->first();

        $transferPayload = null;
        $transactionId = $ledgerEntry->transaction_id;

        if ($transactionId !== '' && $wallet instanceof Wallet) {
            $transfer = Transfer::query()
                ->where('transaction_id', $transactionId)
                ->where(function ($query) use ($wallet): void {
                    $query->where('sender_wallet_id', $wallet->id)
                        ->orWhere('receiver_wallet_id', $wallet->id);
                })
                ->with('hold')
                ->first();

            if ($transfer instanceof Transfer) {
                try {
                    $transferPayload = (new TransferResource($transfer))->resolve();
                } catch (\Throwable $e) {
                    report($e);
                    $transferPayload = [
                        'id' => $transfer->id,
                        'reference' => $transfer->reference,
                        'type' => $transfer->getRawOriginal('type'),
                        'status' => $transfer->getRawOriginal('status'),
                        'currency' => $transfer->currency,
                        'amount' => $transfer->amount,
                        'note' => $transfer->note,
                        'hold' => null,
                    ];
                }
            }
        }

        try {
            $entryPayload = (new StatementEntryResource($ledgerEntry))->resolve();
        } catch (\Throwable $e) {
            report($e);
            $entryPayload = [
                'id' => $ledgerEntry->id,
                'direction' => $ledgerEntry->getRawOriginal('direction'),
                'amount' => (int) $ledgerEntry->amount,
                'currency' => $ledgerEntry->currency,
                'created_at' => $ledgerEntry->created_at,
                'transaction' => $ledgerEntry->transaction !== null
                    ? [
                        'id' => $ledgerEntry->transaction->id,
                        'reference' => $ledgerEntry->transaction->reference,
                        'type' => $ledgerEntry->transaction->getRawOriginal('type'),
                        'status' => $ledgerEntry->transaction->getRawOriginal('status'),
                        'description' => $ledgerEntry->transaction->description,
                        'amount' => $ledgerEntry->transaction->amount,
                    ]
                    : null,
            ];
        }

        $parties = null;
        if ($wallet instanceof Wallet) {
            try {
                $parties = $this->receiptParties->forEntry($ledgerEntry, $wallet);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return Inertia::render('Activity/Show', [
            'entry' => $entryPayload,
            'transfer' => $transferPayload,
            'parties' => $parties,
            'wallet' => $wallet instanceof Wallet
                ? [
                    'id' => $wallet->id,
                    'account_number' => $wallet->account_number,
                    'currency' => $wallet->currency,
                    'available_balance' => max(0, (int) $wallet->balance - (int) $wallet->held_balance),
                    'held_balance' => max(0, (int) $wallet->held_balance),
                    'balance' => max(0, (int) $wallet->balance),
                ]
                : null,
            'receipt' => [
                'issued_at' => now()->toIso8601String(),
                'app' => (string) config('app.name', 'Reton'),
                'user_name' => (string) $user->name,
                'user_email' => (string) $user->email,
            ],
        ]);
    }
}
