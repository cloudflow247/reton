<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum TransactionType: string
{
    case WalletFunding = 'wallet_funding';
    case WalletWithdrawal = 'wallet_withdrawal';
    case InternalTransfer = 'internal_transfer';
    case BankTransfer = 'bank_transfer';
    case Fee = 'fee';
    case Reversal = 'reversal';
    case CallbackHold = 'callback_hold';
    case CallbackRelease = 'callback_release';
    case CallbackRefund = 'callback_refund';
    case RecoveryHold = 'recovery_hold';
    case RecoveryReturn = 'recovery_return';
    case Settlement = 'settlement';
}
