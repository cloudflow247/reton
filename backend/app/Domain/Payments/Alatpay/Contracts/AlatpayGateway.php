<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Contracts;

use App\Domain\Payments\Alatpay\Data\CollectionRequest;
use App\Domain\Payments\Alatpay\Data\CollectionResponse;
use App\Domain\Payments\Alatpay\Data\RemoteTransaction;
use App\Domain\Payments\Alatpay\Data\TransferRequest;
use App\Domain\Payments\Alatpay\Data\TransferResponse;

/**
 * The dedicated AlatPay service layer. All AlatPay HTTP traffic flows through an
 * implementation of this contract — no controller or domain service talks to
 * AlatPay directly.
 */
interface AlatpayGateway
{
    public function createCollection(CollectionRequest $request): CollectionResponse;

    public function fetchTransaction(string $providerReference): ?RemoteTransaction;

    public function initiateTransfer(TransferRequest $request): TransferResponse;

    public function fetchTransfer(string $providerReference): ?RemoteTransaction;
}
