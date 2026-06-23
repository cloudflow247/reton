<?php

declare(strict_types=1);

namespace App\Domain\Recovery\Policies;

use App\Domain\Recovery\Models\Recovery;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;

class RecoveryPolicy
{
    /** Either party may view the recovery. */
    public function view(User $user, Recovery $recovery): bool
    {
        return $this->ownsSender($user, $recovery) || $this->ownsReceiver($user, $recovery);
    }

    /** Only the receiver may return or dispute a recovery. */
    public function respond(User $user, Recovery $recovery): bool
    {
        return $this->ownsReceiver($user, $recovery);
    }

    /** Either party may add evidence. */
    public function contribute(User $user, Recovery $recovery): bool
    {
        return $this->ownsSender($user, $recovery) || $this->ownsReceiver($user, $recovery);
    }

    private function ownsSender(User $user, Recovery $recovery): bool
    {
        return $this->ownsWallet($user, $recovery->senderWallet);
    }

    private function ownsReceiver(User $user, Recovery $recovery): bool
    {
        return $this->ownsWallet($user, $recovery->receiverWallet);
    }

    private function ownsWallet(User $user, ?Wallet $wallet): bool
    {
        return $wallet !== null
            && $wallet->owner_type === $user->getMorphClass()
            && (string) $wallet->owner_id === (string) $user->getKey();
    }
}
