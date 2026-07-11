<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Recovery\Enums\RecoveryStatus;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Transfers\Enums\HoldStatus;
use App\Domain\Transfers\Models\Hold;
use App\Domain\Wallet\Models\Wallet;

/**
 * Soft escrow (`wallets.held_balance`) is denormalized from Hold + Recovery rows.
 * This reconciler is the source-of-truth check / repair for that counter.
 *
 * Invariant: available + held = ledger balance, and
 * held === Σ(active protected holds) + Σ(open recoveries).
 */
final class HeldBalanceReconciler
{
    public function expectedHeldMinor(Wallet $wallet): int
    {
        $activeHolds = (int) Hold::query()
            ->where('status', HoldStatus::Active)
            ->whereHas('transfer', function ($query) use ($wallet): void {
                $query->where('receiver_wallet_id', $wallet->getKey());
            })
            ->sum('amount');

        $openRecoveries = (int) Recovery::query()
            ->where('receiver_wallet_id', $wallet->getKey())
            ->whereIn('status', [RecoveryStatus::Held, RecoveryStatus::Escalated])
            ->sum('amount');

        return max(0, $activeHolds + $openRecoveries);
    }

    /**
     * Force held_balance to match Hold + Recovery truth. Returns the synced value.
     */
    public function sync(Wallet $wallet): int
    {
        $expected = $this->expectedHeldMinor($wallet);

        Wallet::whereKey($wallet->getKey())->update(['held_balance' => $expected]);

        return $expected;
    }

    public function isConsistent(Wallet $wallet): bool
    {
        return (int) $wallet->fresh()->held_balance === $this->expectedHeldMinor($wallet);
    }
}
