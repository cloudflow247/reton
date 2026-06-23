<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Policies;

use App\Domain\Transfers\Models\Transfer;
use App\Models\User;

class TransferPolicy
{
    /**
     * Either party to a transfer may view it.
     */
    public function view(User $user, Transfer $transfer): bool
    {
        return $this->ownsWallet($user, $transfer->senderWallet?->owner_type, $transfer->senderWallet?->owner_id)
            || $this->ownsWallet($user, $transfer->receiverWallet?->owner_type, $transfer->receiverWallet?->owner_id);
    }

    /**
     * Only the sender may release (confirm) a protected transfer.
     */
    public function release(User $user, Transfer $transfer): bool
    {
        return $this->ownsWallet($user, $transfer->senderWallet?->owner_type, $transfer->senderWallet?->owner_id);
    }

    /**
     * Only the sender may raise a callback on a protected transfer.
     */
    public function callback(User $user, Transfer $transfer): bool
    {
        return $this->ownsWallet($user, $transfer->senderWallet?->owner_type, $transfer->senderWallet?->owner_id);
    }

    /**
     * Only the sender may report a wrong-transfer recovery.
     */
    public function recover(User $user, Transfer $transfer): bool
    {
        return $this->ownsWallet($user, $transfer->senderWallet?->owner_type, $transfer->senderWallet?->owner_id);
    }

    private function ownsWallet(User $user, ?string $ownerType, ?string $ownerId): bool
    {
        return $ownerType === $user->getMorphClass()
            && $ownerId !== null
            && (string) $ownerId === (string) $user->getKey();
    }
}
