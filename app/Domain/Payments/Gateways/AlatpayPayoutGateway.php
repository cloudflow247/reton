<?php

declare(strict_types=1);

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Data\RemoteTransaction;
use App\Domain\Payments\Alatpay\Data\TransferRequest;
use App\Domain\Payments\Alatpay\Data\TransferResponse;
use App\Domain\Payments\Contracts\PayoutGateway;

/**
 * Adapts the ALATPay gateway's transfer methods onto the shared payout contract.
 */
final class AlatpayPayoutGateway implements PayoutGateway
{
    public function __construct(private readonly AlatpayGateway $alatpay) {}

    public function supportsOutboundTransfers(): bool
    {
        return $this->alatpay->supportsOutboundTransfers();
    }

    public function initiateTransfer(TransferRequest $request): TransferResponse
    {
        return $this->alatpay->initiateTransfer($request);
    }

    public function fetchTransfer(string $providerReference): ?RemoteTransaction
    {
        return $this->alatpay->fetchTransfer($providerReference);
    }

    public function ping(): void
    {
        if (! $this->supportsOutboundTransfers()) {
            throw new \RuntimeException('ALATPay Debit Wallet is not enabled or missing access key.');
        }
    }
}
