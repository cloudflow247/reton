<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Services;

use App\Domain\Kyc\Models\KycVerificationLog;
use App\Models\User;

/**
 * Immutable audit trail for identity verification (PCI / ISO 27001 control).
 * Never stores raw BVN/NIN — only outcome metadata.
 */
class KycAuditService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(
        User $user,
        string $type,
        string $provider,
        string $status,
        ?string $failureReason = null,
        ?string $ipAddress = null,
        array $meta = [],
    ): KycVerificationLog {
        return KycVerificationLog::query()->create([
            'user_id' => $user->getKey(),
            'type' => $type,
            'provider' => $provider,
            'status' => $status,
            'failure_reason' => $failureReason,
            'ip_address' => $ipAddress,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
