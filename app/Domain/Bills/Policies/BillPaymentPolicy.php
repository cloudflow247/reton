<?php

declare(strict_types=1);

namespace App\Domain\Bills\Policies;

use App\Domain\Bills\Models\BillPayment;
use App\Models\User;

class BillPaymentPolicy
{
    public function view(User $user, BillPayment $bill): bool
    {
        return (string) $bill->user_id === (string) $user->getKey();
    }
}
