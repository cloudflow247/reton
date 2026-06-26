<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Data\DeviceContext;
use App\Domain\Auth\Models\Device;
use App\Models\User;

class DeviceRegistrar
{
    /**
     * Record (or refresh) the device a user is authenticating from.
     */
    public function remember(User $user, ?DeviceContext $context): ?Device
    {
        if ($context === null) {
            return null;
        }

        return Device::updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'fingerprint' => $context->fingerprint,
            ],
            [
                'name' => $context->name,
                'platform' => $context->platform,
                'ip_address' => $context->ipAddress,
                'user_agent' => $context->userAgent,
                'last_seen_at' => now(),
            ],
        );
    }
}
