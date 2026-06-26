<?php

declare(strict_types=1);

namespace App\Domain\Bills\Remita\Gateways;

use App\Domain\Bills\Remita\Contracts\BillProviderGateway;
use App\Domain\Bills\Remita\Data\BillPaymentInstruction;
use App\Domain\Bills\Remita\Data\BillProviderResult;
use App\Domain\Bills\Remita\Data\RemoteBill;
use App\Domain\Bills\Remita\Data\RrrInquiry;
use App\Support\Money\Money;

/**
 * An in-memory bill provider for local development and tests. Deterministic;
 * never touches the network.
 *
 * By default every payment completes immediately. Tests can stage the next
 * outcome with {@see willReturn()} or settle a pending bill out of band with
 * {@see markBill()}.
 */
class FakeBillProvider implements BillProviderGateway
{
    /** @var array<string, array{currency: string, amount: int, status: string}> keyed by provider reference */
    private array $bills = [];

    /** @var array<string, RrrInquiry> keyed by RRR */
    private array $rrrs = [];

    private string $nextStatus = 'completed';

    public function lookupRrr(string $rrr): ?RrrInquiry
    {
        if (isset($this->rrrs[$rrr])) {
            return $this->rrrs[$rrr];
        }

        // Any well-formed 12-digit RRR resolves to a deterministic outstanding
        // bill so the lookup flow is exercisable without prior registration.
        if (preg_match('/^\d{12}$/', $rrr) === 1) {
            return new RrrInquiry(
                rrr: $rrr,
                billerName: 'Federal Inland Revenue Service',
                amount: Money::of(7_500_00, 'NGN'),
                payerName: 'RETON CUSTOMER',
                isPaid: false,
            );
        }

        return null;
    }

    public function payBill(BillPaymentInstruction $instruction): BillProviderResult
    {
        $providerReference = 'RMT-'.$instruction->reference;
        $status = $this->nextStatus;
        $this->nextStatus = 'completed';

        $this->bills[$providerReference] = [
            'currency' => $instruction->amount->currency,
            'amount' => $instruction->amount->amount,
            'status' => $status,
        ];

        return new BillProviderResult($providerReference, $status);
    }

    public function fetchBill(string $providerReference): ?RemoteBill
    {
        $record = $this->bills[$providerReference] ?? null;

        if ($record === null) {
            return null;
        }

        return new RemoteBill(
            providerReference: $providerReference,
            status: $record['status'],
            amount: $record['amount'],
            currency: $record['currency'],
        );
    }

    /**
     * Test/dev helper: register the RRR a subsequent lookup should resolve to.
     */
    public function registerRrr(string $rrr, string $billerName, int $amountMinor, string $payerName = 'RETON CUSTOMER'): void
    {
        $this->rrrs[$rrr] = new RrrInquiry(
            rrr: $rrr,
            billerName: $billerName,
            amount: Money::of($amountMinor, 'NGN'),
            payerName: $payerName,
            isPaid: false,
        );
    }

    /**
     * Test/dev helper: force the outcome of the next payBill() call.
     */
    public function willReturn(string $status): void
    {
        $this->nextStatus = $status;
    }

    /**
     * Test/dev helper: change a recorded bill's status (simulates the provider
     * confirming or failing a bill left pending at submission).
     */
    public function markBill(string $providerReference, string $status): void
    {
        if (isset($this->bills[$providerReference])) {
            $this->bills[$providerReference]['status'] = $status;
        }
    }
}
