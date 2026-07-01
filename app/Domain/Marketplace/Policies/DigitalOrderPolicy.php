<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Policies;

use App\Domain\Marketplace\Models\DigitalOrder;
use App\Models\User;

class DigitalOrderPolicy
{
    public function view(User $user, DigitalOrder $order): bool
    {
        return $this->isParty($user, $order);
    }

    public function deliver(User $user, DigitalOrder $order): bool
    {
        return (string) $user->getKey() === (string) $order->seller_id;
    }

    public function confirm(User $user, DigitalOrder $order): bool
    {
        return (string) $user->getKey() === (string) $order->buyer_id;
    }

    public function dispute(User $user, DigitalOrder $order): bool
    {
        return (string) $user->getKey() === (string) $order->buyer_id;
    }

    private function isParty(User $user, DigitalOrder $order): bool
    {
        $id = (string) $user->getKey();

        return $id === (string) $order->buyer_id || $id === (string) $order->seller_id;
    }
}
