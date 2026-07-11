<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Dashboard\Services\DashboardSummaryService;
use App\Domain\Kyc\Services\KycService;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Support\StatementMoneyFlow;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StatementEntryResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const RECENT_LIMIT = 5;

    public function __construct(
        private readonly DashboardSummaryService $summary,
        private readonly KycService $kyc,
        private readonly StaticAccountService $staticAccounts,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $credited = 0;

        try {
            $credited = $this->staticAccounts->pollActiveForUser($user);
        } catch (\Throwable $e) {
            report($e);
            $request->session()->flash(
                'error',
                'Could not check ALATPay for new deposits. Open Add Money and try again in a moment.',
            );
        }

        $wallet = $user->wallets()->with([
            'staticAccount' => fn ($q) => $q->where('status', StaticAccountStatus::Active),
        ])->first();

        if ($credited > 0) {
            $request->session()->flash(
                'success',
                $credited === 1
                    ? 'Deposit received — your balance is updated.'
                    : "{$credited} deposits received — your balance is updated.",
            );
        }

        $entries = $wallet instanceof Wallet
            ? $this->recentEntries($wallet)
            : new Collection;

        return Inertia::render('Dashboard', [
            'summary' => $this->summary->forUser($user)->toArray(),
            'kycTier' => $this->kyc->forUser($user)->tier->value,
            'activity' => StatementEntryResource::collection($entries)->resolve(),
            'activityFlow' => StatementMoneyFlow::fromEntries($entries),
            'depositAccount' => $this->depositAccountPayload($wallet),
        ]);
    }

    /**
     * Latest statement lines for the dashboard preview. Limit must match the
     * rows rendered so money-in/out totals stay consistent with the list.
     *
     * @return Collection<int, LedgerEntry>
     */
    private function recentEntries(Wallet $wallet): Collection
    {
        return LedgerEntry::where('ledger_account_id', $wallet->ledger_account_id)
            ->with('transaction')
            ->latest('created_at')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    /**
     * @return array{account_number: string, account_name: string|null, bank_name: string|null}|null
     */
    private function depositAccountPayload(?Wallet $wallet): ?array
    {
        if (! $wallet instanceof Wallet) {
            return null;
        }

        $static = $wallet->staticAccount;

        if ($static === null || ! $static->isActive() || blank($static->account_number)) {
            return null;
        }

        return [
            'account_number' => (string) $static->account_number,
            'account_name' => $static->account_name,
            'bank_name' => $static->bank_name,
        ];
    }
}
