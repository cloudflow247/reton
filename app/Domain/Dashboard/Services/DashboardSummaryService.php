<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Callback\Models\Callback;
use App\Domain\Dashboard\Data\DashboardSummary;
use App\Domain\Fraud\Models\FraudAlert;
use App\Domain\Recovery\Enums\RecoveryStatus;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Enums\TransferType;
use App\Domain\Transfers\Models\Transfer;
use App\Models\User;

class DashboardSummaryService
{
    public function forUser(User $user): DashboardSummary
    {
        $walletIds = $user->wallets()->pluck('id');

        if ($walletIds->isEmpty()) {
            return new DashboardSummary(0, 0, 0, 0, 100);
        }

        $transferScope = static function ($query) use ($walletIds): void {
            $query->whereIn('sender_wallet_id', $walletIds)
                ->orWhereIn('receiver_wallet_id', $walletIds);
        };

        $pendingCallbacks = Callback::query()
            ->whereHas('transfer', $transferScope)
            ->whereIn('status', [CallbackStatus::Pending, CallbackStatus::Escalated])
            ->count();

        $openRecoveries = Recovery::query()
            ->whereHas('transfer', $transferScope)
            ->whereIn('status', [RecoveryStatus::Held, RecoveryStatus::Escalated])
            ->count();

        $protectedPending = Transfer::query()
            ->where($transferScope)
            ->where('type', TransferType::Protected)
            ->where('status', TransferStatus::Held)
            ->count();

        $openFraudAlerts = FraudAlert::query()
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->count();

        $trustScore = max(
            0,
            min(100, 100 - ($openFraudAlerts * 12) - ($openRecoveries * 6) - ($pendingCallbacks * 4)),
        );

        return new DashboardSummary(
            pending_callbacks: $pendingCallbacks,
            open_recoveries: $openRecoveries,
            protected_transfers_pending: $protectedPending,
            open_fraud_alerts: $openFraudAlerts,
            trust_score: $trustScore,
        );
    }
}
