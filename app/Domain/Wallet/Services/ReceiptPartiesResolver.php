<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Models\Payout;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;

/**
 * Builds human-readable From / To parties for statement receipts.
 *
 * Channels: Reton→Reton transfers, bank→wallet deposits, wallet→bank payouts.
 *
 * @phpstan-type ReceiptParty array{kind: string, label: string, name: string|null, reton_id: string|null, bank_name: string|null, account_number: string|null, detail: string|null}
 * @phpstan-type ReceiptParties array{channel: string, channel_label: string, from: ReceiptParty, to: ReceiptParty, funding_account?: string|null}
 */
final class ReceiptPartiesResolver
{
    /**
     * @return ReceiptParties|null
     */
    public function forEntry(LedgerEntry $entry, Wallet $viewerWallet): ?array
    {
        $transactionId = $entry->transaction_id;

        if ($transactionId === '') {
            return null;
        }

        $transfer = Transfer::query()
            ->where('transaction_id', $transactionId)
            ->with(['senderWallet', 'receiverWallet'])
            ->first();

        if ($transfer instanceof Transfer) {
            return $this->fromTransfer($transfer);
        }

        $deposit = Deposit::query()
            ->where('transaction_id', $transactionId)
            ->first();

        if ($deposit instanceof Deposit) {
            return $this->fromDeposit($deposit, $viewerWallet);
        }

        $payout = Payout::query()
            ->where(function ($query) use ($transactionId): void {
                $query->where('reservation_transaction_id', $transactionId)
                    ->orWhere('settlement_transaction_id', $transactionId);
            })
            ->first();

        if ($payout instanceof Payout) {
            return $this->fromPayout($payout, $viewerWallet);
        }

        return $this->fromTransactionMetadata($entry->transaction, $viewerWallet);
    }

    /**
     * @return ReceiptParties
     */
    private function fromTransfer(Transfer $transfer): array
    {
        $sender = $this->walletParty($transfer->senderWallet, 'Sender', 'From');
        $receiver = $this->walletParty($transfer->receiverWallet, 'Receiver', 'To');

        return [
            'channel' => 'reton_transfer',
            'channel_label' => $transfer->isProtected() ? 'Protected Reton transfer' : 'Reton transfer',
            'from' => $sender,
            'to' => $receiver,
        ];
    }

    /**
     * @return ReceiptParties
     */
    private function fromDeposit(Deposit $deposit, Wallet $viewerWallet): array
    {
        $meta = is_array($deposit->metadata) ? $deposit->metadata : [];
        $bankTransfer = is_array($meta['bank_transfer'] ?? null) ? $meta['bank_transfer'] : [];
        $virtual = is_array($deposit->virtual_account) ? $deposit->virtual_account : [];

        $static = null;
        if (filled($meta['static_account_id'] ?? null)) {
            $static = StaticAccount::query()->find($meta['static_account_id']);
        }
        if (! $static instanceof StaticAccount) {
            $static = StaticAccount::query()
                ->where('wallet_id', $deposit->wallet_id)
                ->whereNotNull('account_number')
                ->latest()
                ->first();
        }

        $bankName = $this->firstFilled([
            $bankTransfer['bank_name'] ?? null,
            $virtual['bank_name'] ?? null,
            $static?->bank_name,
            'Bank transfer',
        ]);

        $payer = $this->firstFilled([
            $bankTransfer['payer_name'] ?? null,
            $meta['payer_name'] ?? null,
        ]);

        $narration = $this->firstFilled([
            $bankTransfer['narration'] ?? null,
            $meta['narration'] ?? null,
        ]);

        $fundingAccount = $this->firstFilled([
            $virtual['account_number'] ?? null,
            $static?->account_number,
        ]);

        $fromDetail = collect([$payer, $narration])->filter()->implode(' · ') ?: null;

        return [
            'channel' => 'bank_deposit',
            'channel_label' => 'Bank funding',
            'from' => $this->party(
                kind: 'bank',
                label: 'From bank',
                name: $payer,
                bankName: $bankName,
                accountNumber: null,
                detail: $fromDetail,
            ),
            'to' => $this->walletParty($viewerWallet, 'Your wallet', 'To wallet'),
            'funding_account' => $fundingAccount,
        ];
    }

    /**
     * @return ReceiptParties
     */
    private function fromPayout(Payout $payout, Wallet $viewerWallet): array
    {
        return [
            'channel' => 'bank_payout',
            'channel_label' => 'Cash out to bank',
            'from' => $this->walletParty($viewerWallet, 'Your wallet', 'From wallet'),
            'to' => $this->party(
                kind: 'bank',
                label: 'To bank',
                name: $payout->account_name,
                bankName: null,
                accountNumber: $payout->account_number,
                detail: filled($payout->bank_code) ? 'Bank code '.$payout->bank_code : null,
            ),
        ];
    }

    /**
     * @return ReceiptParties|null
     */
    private function fromTransactionMetadata(?Transaction $transaction, Wallet $viewerWallet): ?array
    {
        if (! $transaction instanceof Transaction) {
            return null;
        }

        $meta = is_array($transaction->metadata) ? $transaction->metadata : [];
        $fromWalletId = $meta['from_wallet_id'] ?? $meta['sender_wallet_id'] ?? null;
        $toWalletId = $meta['to_wallet_id'] ?? $meta['receiver_wallet_id'] ?? null;

        if (is_string($fromWalletId) && is_string($toWalletId)) {
            $fromWallet = Wallet::query()->find($fromWalletId);
            $toWallet = Wallet::query()->find($toWalletId);
            $type = (string) ($transaction->getRawOriginal('type') ?? $transaction->type->value);
            $isRefund = str_contains(strtolower($type), 'refund')
                || str_contains(strtolower((string) $transaction->description), 'refund');

            return [
                'channel' => $isRefund ? 'reton_refund' : 'reton_transfer',
                'channel_label' => $isRefund ? 'Protected transfer refund' : 'Reton transfer',
                'from' => $this->walletParty($fromWallet, 'Sender', 'From'),
                'to' => $this->walletParty($toWallet, 'Receiver', 'To'),
            ];
        }

        $bank = is_array($meta['bank_transfer'] ?? null) ? $meta['bank_transfer'] : [];

        if ($bank === [] && ! filled($meta['deposit_id'] ?? null)) {
            return null;
        }

        $bankName = $this->firstFilled([
            $bank['bank_name'] ?? null,
            'Bank transfer',
        ]);

        return [
            'channel' => 'bank_deposit',
            'channel_label' => 'Bank funding',
            'from' => $this->party(
                kind: 'bank',
                label: 'From bank',
                name: $this->firstFilled([$bank['payer_name'] ?? null]),
                bankName: $bankName,
                accountNumber: null,
                detail: $this->firstFilled([$bank['narration'] ?? null]),
            ),
            'to' => $this->walletParty($viewerWallet, 'Your wallet', 'To wallet'),
        ];
    }

    /**
     * @return ReceiptParty
     */
    private function walletParty(?Wallet $wallet, string $fallbackName, string $label): array
    {
        if (! $wallet instanceof Wallet) {
            return $this->party('reton', $label, $fallbackName, null, null, null, null);
        }

        $name = $fallbackName;
        if ($wallet->owner_type === User::class && filled($wallet->owner_id)) {
            $owner = User::query()->find($wallet->owner_id);
            if ($owner instanceof User) {
                $name = (string) $owner->name;
            }
        }

        return $this->party(
            kind: 'reton',
            label: $label,
            name: $name,
            retonId: $wallet->account_number,
            bankName: null,
            accountNumber: null,
            detail: filled($wallet->account_number) ? 'Reton ID '.$wallet->account_number : null,
        );
    }

    /**
     * @return ReceiptParty
     */
    private function party(
        string $kind,
        string $label,
        ?string $name,
        ?string $retonId = null,
        ?string $bankName = null,
        ?string $accountNumber = null,
        ?string $detail = null,
    ): array {
        return [
            'kind' => $kind,
            'label' => $label,
            'name' => $name,
            'reton_id' => $retonId,
            'bank_name' => $bankName,
            'account_number' => $accountNumber,
            'detail' => $detail,
        ];
    }

    /**
     * @param  list<mixed>  $values
     */
    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
