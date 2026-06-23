<?php

declare(strict_types=1);

namespace App\Domain\Callback\Policies;

use App\Domain\Callback\Models\Callback;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;

class CallbackPolicy
{
    /** Either party to the underlying transfer may view the callback. */
    public function view(User $user, Callback $callback): bool
    {
        return $this->ownsSender($user, $callback) || $this->ownsReceiver($user, $callback);
    }

    /** Only the receiver may respond (accept/reject) to a callback. */
    public function respond(User $user, Callback $callback): bool
    {
        return $this->ownsReceiver($user, $callback);
    }

    /** Either party may contribute evidence. */
    public function contribute(User $user, Callback $callback): bool
    {
        return $this->ownsSender($user, $callback) || $this->ownsReceiver($user, $callback);
    }

    private function ownsSender(User $user, Callback $callback): bool
    {
        return $this->ownsWallet($user, $callback->transfer?->senderWallet);
    }

    private function ownsReceiver(User $user, Callback $callback): bool
    {
        return $this->ownsWallet($user, $callback->transfer?->receiverWallet);
    }

    private function ownsWallet(User $user, ?Wallet $wallet): bool
    {
        return $wallet !== null
            && $wallet->owner_type === $user->getMorphClass()
            && (string) $wallet->owner_id === (string) $user->getKey();
    }
}
