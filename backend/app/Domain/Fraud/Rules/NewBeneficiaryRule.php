<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Rules;

use App\Domain\Fraud\Contracts\FraudRule;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Data\FraudSignal;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Models\Transfer;

class NewBeneficiaryRule implements FraudRule
{
    public function evaluate(FraudContext $context): ?FraudSignal
    {
        if ($context->beneficiary === null) {
            return null;
        }

        $seenBefore = Transfer::where('initiated_by', $context->user->getKey())
            ->where('receiver_wallet_id', $context->beneficiary->getKey())
            ->where('status', TransferStatus::Completed->value)
            ->exists();

        if ($seenBefore) {
            return null;
        }

        return new FraudSignal(
            'new_beneficiary',
            (int) config('reton.fraud.new_beneficiary_points', 15),
            'First transfer to this beneficiary.',
        );
    }
}
