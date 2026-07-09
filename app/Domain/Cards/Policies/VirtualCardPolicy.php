<?php

declare(strict_types=1);

namespace App\Domain\Cards\Policies;

use App\Domain\Cards\Models\VirtualCard;
use App\Models\User;

class VirtualCardPolicy
{
    public function view(User $user, VirtualCard $card): bool
    {
        return $card->user_id === $user->getKey();
    }

    public function operate(User $user, VirtualCard $card): bool
    {
        return $card->user_id === $user->getKey();
    }
}
