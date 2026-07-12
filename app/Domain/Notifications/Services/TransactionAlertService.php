<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Domain\Fees\Enums\FeeRail;
use App\Domain\Fees\Services\PlatformFeeService;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Wallet\Models\Wallet;
use App\Events\Wallet\WalletFundsMoved;
use App\Mail\WalletTransactionMail;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransactionAlertService
{
    public function __construct(
        private readonly PlatformMailService $mail,
        private readonly SmsNotificationService $sms,
        private readonly PlatformFeeService $fees,
    ) {}

    public function handle(WalletFundsMoved $event): void
    {
        $wallet = $event->wallet->loadMissing('owner');
        $owner = $wallet->owner;

        if (! $owner instanceof User) {
            return;
        }

        // Avoid recursive alerts when we debit the SMS fee itself.
        $idempotencyKey = (string) ($event->transaction->idempotency_key ?? '');
        if (str_starts_with($idempotencyKey, 'sms-alert-fee:')
            || str_starts_with($idempotencyKey, 'fee:sms_alert:')) {
            return;
        }

        $metadata = is_array($event->transaction->metadata) ? $event->transaction->metadata : [];
        if (($metadata['reason'] ?? null) === 'sms_notification_fee') {
            return;
        }

        if ($event->transaction->type === TransactionType::Fee) {
            return;
        }

        $this->notify($owner, $wallet, $event->transaction, $event->direction, $event->amount);
    }

    public function notify(
        User $user,
        Wallet $wallet,
        Transaction $transaction,
        string $direction,
        Money $amount,
    ): void {
        $balance = Money::of((int) $wallet->fresh()->balance, $wallet->currency);

        if ($user->wantsEmailAlerts()) {
            $this->sendEmail($user, $wallet, $transaction, $direction, $amount, $balance);
        }

        if ($user->wantsSmsAlerts()) {
            $this->sendSms($user, $wallet, $transaction, $direction, $amount, $balance);
        }
    }

    private function sendEmail(
        User $user,
        Wallet $wallet,
        Transaction $transaction,
        string $direction,
        Money $amount,
        Money $balance,
    ): void {
        if (! $this->mail->isEnabled() || blank($user->email)) {
            return;
        }

        try {
            Mail::to($user->email)->send(new WalletTransactionMail(
                user: $user,
                direction: $direction,
                amount: $amount,
                balance: $balance,
                reference: (string) $transaction->reference,
                description: (string) ($transaction->description ?: 'Wallet transaction'),
                occurredAt: $transaction->posted_at ?? now(),
                walletAccountNumber: (string) ($wallet->account_number ?? ''),
            ));
        } catch (\Throwable $e) {
            Log::warning('transaction_alert.email_failed', [
                'user_id' => $user->getKey(),
                'transaction_id' => $transaction->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendSms(
        User $user,
        Wallet $wallet,
        Transaction $transaction,
        string $direction,
        Money $amount,
        Money $balance,
    ): void {
        if (! $this->sms->isEnabled() || blank($user->phone)) {
            return;
        }

        $fee = $this->fees->calculate(FeeRail::SmsAlert, Money::zero($wallet->currency));

        if ($fee->isPositive()) {
            try {
                $this->fees->chargeWallet(
                    $wallet,
                    FeeRail::SmsAlert,
                    Money::zero($wallet->currency),
                    'fee:sms_alert:'.$transaction->getKey(),
                );
                $balance = Money::of((int) $wallet->fresh()->balance, $wallet->currency);
            } catch (\Throwable $e) {
                Log::info('transaction_alert.sms_skipped_no_fee_funds', [
                    'user_id' => $user->getKey(),
                    'transaction_id' => $transaction->getKey(),
                    'error' => $e->getMessage(),
                ]);

                return;
            }
        }

        $label = $direction === 'credit' ? 'Credit' : 'Debit';
        $formattedAmount = $this->formatMoney($amount);
        $formattedBalance = $this->formatMoney($balance);
        $message = sprintf(
            'Reton %s: %s. Bal: %s. Ref: %s',
            $label,
            $formattedAmount,
            $formattedBalance,
            $transaction->reference,
        );

        $this->sms->sendAlert((string) $user->phone, $message);
    }

    private function formatMoney(Money $money): string
    {
        $symbol = $money->currency === 'NGN' ? 'NGN ' : $money->currency.' ';

        return $symbol.number_format($money->amount / 100, 2);
    }
}
