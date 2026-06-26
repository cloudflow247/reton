<?php

declare(strict_types=1);

namespace App\Domain\Payments\Policies;

use App\Domain\Payments\Models\Deposit;
use App\Models\User;

class DepositPolicy
{
    public function view(User $user, Deposit $deposit): bool
    {
        return (string) $deposit->user_id === (string) $user->getKey();
    }
}
