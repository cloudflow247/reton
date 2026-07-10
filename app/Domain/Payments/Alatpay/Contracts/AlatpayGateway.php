<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Contracts;

use App\Domain\Payments\Alatpay\Data\CollectionRequest;
use App\Domain\Payments\Alatpay\Data\CollectionResponse;
use App\Domain\Payments\Alatpay\Data\PaymentLinkRequest;
use App\Domain\Payments\Alatpay\Data\PaymentLinkResponse;
use App\Domain\Payments\Alatpay\Data\RemoteTransaction;
use App\Domain\Payments\Alatpay\Data\StaticAccountProvisionResponse;
use App\Domain\Payments\Alatpay\Data\StaticAccountRequest;
use App\Domain\Payments\Alatpay\Data\StaticAccountResponse;
use App\Domain\Payments\Alatpay\Data\StaticAccountTransaction;
use App\Domain\Payments\Alatpay\Data\StaticAccountVerifyRequest;
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

    public function createPaymentLink(PaymentLinkRequest $request): PaymentLinkResponse;

    public function fetchTransaction(string $providerReference): ?RemoteTransaction;

    public function initiateTransfer(TransferRequest $request): TransferResponse;

    public function fetchTransfer(string $providerReference): ?RemoteTransaction;

    /**
     * Verify secret key + Business ID against the Static Wallet API
     * (the same product used for BVN OTP). Must not treat unrelated 404s as success.
     */
    public function pingStaticWallet(): void;

    public function provisionStaticAccount(StaticAccountRequest $request): StaticAccountProvisionResponse;

    public function verifyStaticAccount(StaticAccountVerifyRequest $request): StaticAccountResponse;

    /**
     * List static wallets for the configured business.
     *
     * @return list<\App\Domain\Payments\Alatpay\Data\StaticAccountSummary>
     */
    public function listStaticAccounts(int $page = 1, int $limit = 50, int $status = 1): array;

    /** @return array<int, StaticAccountTransaction> */
    public function fetchStaticAccountTransactions(string $accountNumber, int $page = 1, int $limit = 50): array;
}
