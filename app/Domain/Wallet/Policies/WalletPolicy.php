<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Policies;

use App\Domain\Wallet\Models\Wallet;
use App\Models\User;

class WalletPolicy
{
    public function view(User $user, Wallet $wallet): bool
    {
        return $this->owns($user, $wallet);
    }

    public function operate(User $user, Wallet $wallet): bool
    {
        return $this->owns($user, $wallet);
    }

    private function owns(User $user, Wallet $wallet): bool
    {
        return $wallet->owner_type === $user->getMorphClass()
            && (string) $wallet->owner_id === (string) $user->getKey();
    }
}
