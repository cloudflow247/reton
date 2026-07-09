<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Services;

use App\Domain\Kyc\Exceptions\KycLimitExceededException;
use App\Domain\Kyc\Models\UserKyc;
use App\Domain\Payments\Enums\DepositStatus;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Validation\ValidationException;

class KycLimitService
{
    public function __construct(private readonly KycService $kyc) {}

    public function assertCanCredit(User $user, Wallet $wallet, Money $amount): void
    {
        $profile = $this->kyc->forUser($user);
        $limits = $this->kyc->limitsFor($profile);
        $tier = $profile->tier->value;

        if ($amount->amount > $limits['single_transaction_max']) {
            throw ValidationException::withMessages([
                'amount' => [KycLimitExceededException::singleTransaction($tier, $limits['single_transaction_max'])->getMessage()],
            ]);
        }

        $todayInflow = $this->todayInflowMinor($user, $wallet->currency);

        if ($todayInflow + $amount->amount > $limits['daily_inflow_max']) {
            throw ValidationException::withMessages([
                'amount' => [KycLimitExceededException::dailyInflow($tier, $limits['daily_inflow_max'])->getMessage()],
            ]);
        }

        if ($wallet->balance + $amount->amount > $limits['wallet_balance_max']) {
            throw ValidationException::withMessages([
                'amount' => [KycLimitExceededException::walletBalance($tier, $limits['wallet_balance_max'])->getMessage()],
            ]);
        }
    }

    public function assertCanSpend(User $user, Wallet $wallet, Money $amount): void
    {
        $profile = $this->kyc->forUser($user);
        $limits = $this->kyc->limitsFor($profile);
        $tier = $profile->tier->value;

        if ($amount->amount > $limits['single_transaction_max']) {
            throw ValidationException::withMessages([
                'amount' => [KycLimitExceededException::singleTransaction($tier, $limits['single_transaction_max'])->getMessage()],
            ]);
        }
    }

    private function todayInflowMinor(User $user, string $currency): int
    {
        return (int) Deposit::query()
            ->where('user_id', $user->getKey())
            ->where('currency', $currency)
            ->where('status', DepositStatus::Completed)
            ->whereDate('paid_at', today())
            ->sum('amount');
    }
}
