<?php

declare(strict_types=1);

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\Alatpay\Data\RemoteTransaction;
use App\Domain\Payments\Alatpay\Data\TransferRequest;
use App\Domain\Payments\Alatpay\Data\TransferResponse;

/**
 * Outbound bank payouts (withdrawals). Implementations: Paystack Transfers,
 * ALATPay/Wema Debit Wallet adapter. Collections stay on AlatpayGateway.
 */
interface PayoutGateway
{
    public function supportsOutboundTransfers(): bool;

    public function initiateTransfer(TransferRequest $request): TransferResponse;

    public function fetchTransfer(string $providerReference): ?RemoteTransaction;

    /** Optional health check used by Admin → Integrations. */
    public function ping(): void;
}
