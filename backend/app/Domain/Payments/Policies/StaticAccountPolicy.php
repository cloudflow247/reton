<?php

declare(strict_types=1);

namespace App\Domain\Payments\Policies;

use App\Domain\Payments\Models\StaticAccount;
use App\Models\User;

class StaticAccountPolicy
{
    public function view(User $user, StaticAccount $staticAccount): bool
    {
        return (string) $staticAccount->user_id === (string) $user->getKey();
    }
}
