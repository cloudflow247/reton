<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Dashboard\Services\DashboardSummaryService;
use App\Domain\Kyc\Services\KycService;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StatementEntryResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardSummaryService $summary,
        private readonly KycService $kyc,
        private readonly StaticAccountService $staticAccounts,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->staticAccounts->pollActiveForUser($user);
        $wallet = $user->wallets()->first();

        return Inertia::render('Dashboard', [
            'summary' => $this->summary->forUser($user)->toArray(),
            'kycTier' => $this->kyc->forUser($user)->tier->value,
            'activity' => $wallet instanceof Wallet
                ? StatementEntryResource::collection($this->recentEntries($wallet))->resolve()
                : [],
        ]);
    }

    /**
     * The latest few statement lines for the dashboard preview. Full history
     * lives on the Activity page.
     *
     * @return Collection<int, LedgerEntry>
     */
    private function recentEntries(Wallet $wallet): Collection
    {
        return LedgerEntry::where('ledger_account_id', $wallet->ledger_account_id)
            ->with('transaction')
            ->latest('created_at')
            ->limit(6)
            ->get();
    }
}
