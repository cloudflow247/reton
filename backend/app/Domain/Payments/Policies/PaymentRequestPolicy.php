<?php

declare(strict_types=1);

namespace App\Domain\Payments\Policies;

use App\Domain\Payments\Models\PaymentRequest;
use App\Models\User;

class PaymentRequestPolicy
{
    public function view(User $user, PaymentRequest $paymentRequest): bool
    {
        return (string) $paymentRequest->requester_user_id === (string) $user->getKey();
    }

    public function cancel(User $user, PaymentRequest $paymentRequest): bool
    {
        return (string) $paymentRequest->requester_user_id === (string) $user->getKey();
    }
}
