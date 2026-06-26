<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StatementEntryResource;
use App\Http\Resources\Api\V1\TransferResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
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
            ->limit(50)
            ->get();

        $statement = $wallet instanceof Wallet
            ? LedgerEntry::where('ledger_account_id', $wallet->ledger_account_id)
                ->with('transaction')
                ->latest('created_at')
                ->limit(50)
                ->get()
            : collect();

        return Inertia::render('Activity', [
            'transfers' => TransferResource::collection($transfers),
            'statement' => StatementEntryResource::collection($statement),
        ]);
    }
}
