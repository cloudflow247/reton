<?php

declare(strict_types=1);

namespace App\Domain\Bills\Remita\Contracts;

use App\Domain\Bills\Remita\Data\BillPaymentInstruction;
use App\Domain\Bills\Remita\Data\BillProviderResult;
use App\Domain\Bills\Remita\Data\RemoteBill;
use App\Domain\Bills\Remita\Data\RrrInquiry;

/**
 * The dedicated bill-payment provider layer (Remita and friends). All bill
 * provider traffic flows through an implementation of this contract - no
 * controller or domain service talks to a provider directly.
 */
interface BillProviderGateway
{
    /** Resolve a Remita Retrieval Reference, or null when it is unknown. */
    public function lookupRrr(string $rrr): ?RrrInquiry;

    public function payBill(BillPaymentInstruction $instruction): BillProviderResult;

    /** The provider's current view of a submitted bill, for reconciliation. */
    public function fetchBill(string $providerReference): ?RemoteBill;
}
