<?php

declare(strict_types=1);

namespace App\Domain\Payments\Policies;

use App\Domain\Payments\Models\Payout;
use App\Models\User;

class PayoutPolicy
{
    public function view(User $user, Payout $payout): bool
    {
        return (string) $payout->user_id === (string) $user->getKey();
    }
}
