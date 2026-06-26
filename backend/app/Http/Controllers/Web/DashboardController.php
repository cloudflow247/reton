<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StatementEntryResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $wallet = $user->wallets()->first();

        return Inertia::render('Dashboard', [
            'activity' => $wallet instanceof Wallet
                ? StatementEntryResource::collection($this->recentEntries($wallet))
                : [],
        ]);
    }

    /**
     * The latest few statement lines for the dashboard preview. Full history
     * lives on the Activity page.
     */
    private function recentEntries(Wallet $wallet)
    {
        return LedgerEntry::where('ledger_account_id', $wallet->ledger_account_id)
            ->with('transaction')
            ->latest('created_at')
            ->limit(6)
            ->get();
    }
}
