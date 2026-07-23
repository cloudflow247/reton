<?php

declare(strict_types=1);

namespace App\Domain\Support\Services;

use App\Domain\Bills\Models\BillPayment;
use App\Domain\Callback\Models\Callback;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Models\Payout;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Support\Data\TransactionLookupResult;
use App\Domain\Transfers\Models\Transfer;
use App\Models\User;

/**
 * Scoped transaction lookup - only returns records the user owns or participates in.
 */
class TransactionLookupService
{
    public function extractReference(string $text): ?string
    {
        if (preg_match('/\b([A-Z]{2,4}-[A-Z0-9]{10,})\b/i', $text, $matches) !== 1) {
            return null;
        }

        return strtoupper($matches[1]);
    }

    public function lookup(User $user, string $reference): ?TransactionLookupResult
    {
        $reference = strtoupper(trim($reference));
        $walletIds = $user->wallets()->pluck('id')->all();

        if ($walletIds === []) {
            return null;
        }

        $transferScope = static function ($query) use ($walletIds): void {
            $query->whereIn('sender_wallet_id', $walletIds)
                ->orWhereIn('receiver_wallet_id', $walletIds);
        };

        $transfer = Transfer::query()
            ->where('reference', $reference)
            ->where($transferScope)
            ->first();

        if ($transfer instanceof Transfer) {
            return new TransactionLookupResult(
                kind: 'transfer',
                reference: $transfer->reference,
                amountMinor: $transfer->amount,
                currency: $transfer->currency,
                status: $transfer->status->value,
                summary: sprintf(
                    '%s transfer - %s',
                    ucfirst($transfer->type->value),
                    $transfer->status->value,
                ),
                actions: [
                    ['label' => 'View activity', 'href' => '/activity'],
                    ['label' => 'Protection center', 'href' => '/protection'],
                ],
                relatedId: (string) $transfer->id,
            );
        }

        $deposit = Deposit::query()
            ->where('reference', $reference)
            ->where('user_id', $user->id)
            ->first();

        if ($deposit instanceof Deposit) {
            return new TransactionLookupResult(
                kind: 'deposit',
                reference: $deposit->reference,
                amountMinor: $deposit->amount,
                currency: $deposit->currency,
                status: $deposit->status->value,
                summary: 'Wallet deposit - '.$deposit->status->value,
                actions: [
                    ['label' => 'Add money', 'href' => '/add-money'],
                    ['label' => 'View activity', 'href' => '/activity'],
                ],
                relatedId: (string) $deposit->id,
            );
        }

        $callback = Callback::query()
            ->where('reference', $reference)
            ->whereHas('transfer', $transferScope)
            ->with('transfer')
            ->first();

        if ($callback instanceof Callback) {
            $callbackTransfer = $callback->transfer;

            if ($callbackTransfer === null) {
                return null;
            }

            return new TransactionLookupResult(
                kind: 'callback',
                reference: $callback->reference,
                amountMinor: $callbackTransfer->amount,
                currency: $callbackTransfer->currency,
                status: $callback->status->value,
                summary: 'Callback dispute - '.$callback->status->value,
                actions: [
                    ['label' => 'Open protection center', 'href' => '/protection'],
                ],
                relatedId: (string) $callback->id,
            );
        }

        $recovery = Recovery::query()
            ->where('reference', $reference)
            ->whereHas('transfer', $transferScope)
            ->first();

        if ($recovery instanceof Recovery) {
            return new TransactionLookupResult(
                kind: 'recovery',
                reference: $recovery->reference,
                amountMinor: $recovery->amount,
                currency: $recovery->currency,
                status: $recovery->status->value,
                summary: 'Wrong-transfer recovery - '.$recovery->status->value,
                actions: [
                    ['label' => 'Open protection center', 'href' => '/protection'],
                ],
                relatedId: (string) $recovery->id,
            );
        }

        $bill = BillPayment::query()
            ->where('reference', $reference)
            ->where('user_id', $user->id)
            ->first();

        if ($bill instanceof BillPayment) {
            return new TransactionLookupResult(
                kind: 'bill',
                reference: $bill->reference,
                amountMinor: $bill->amount,
                currency: $bill->currency,
                status: $bill->status->value,
                summary: sprintf('Bill payment - %s (%s)', $bill->biller_name, $bill->status->value),
                actions: [
                    ['label' => 'View activity', 'href' => '/activity'],
                    ['label' => 'Pay a bill', 'href' => '/bills'],
                ],
                relatedId: (string) $bill->id,
            );
        }

        $payout = Payout::query()
            ->where('reference', $reference)
            ->where('user_id', $user->id)
            ->first();

        if ($payout instanceof Payout) {
            return new TransactionLookupResult(
                kind: 'payout',
                reference: $payout->reference,
                amountMinor: $payout->amount,
                currency: $payout->currency,
                status: $payout->status->value,
                summary: 'Bank withdrawal - '.$payout->status->value,
                actions: [
                    ['label' => 'Withdraw', 'href' => '/withdraw'],
                    ['label' => 'View activity', 'href' => '/activity'],
                ],
                relatedId: (string) $payout->id,
            );
        }

        return null;
    }
}
