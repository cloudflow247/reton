<?php

declare(strict_types=1);

namespace App\Domain\Fraud\Rules;

use App\Domain\Auth\Models\Device;
use App\Domain\Fraud\Contracts\FraudRule;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Data\FraudSignal;

class NewDeviceRule implements FraudRule
{
    public function evaluate(FraudContext $context): ?FraudSignal
    {
        if ($context->deviceFingerprint === null) {
            return null;
        }

        $known = Device::where('user_id', $context->user->getKey())
            ->where('fingerprint', $context->deviceFingerprint)
            ->exists();

        if ($known) {
            return null;
        }

        return new FraudSignal(
            'new_device',
            (int) config('reton.fraud.new_device_points', 30),
            'Transaction initiated from an unrecognised device.',
        );
    }
}
