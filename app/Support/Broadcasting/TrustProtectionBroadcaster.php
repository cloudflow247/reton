<?php

declare(strict_types=1);

namespace App\Support\Broadcasting;

use App\Domain\Callback\Models\Callback;
use App\Domain\Transfers\Models\Transfer;
use App\Events\Trust\TrustProtectionChanged;
use App\Models\User;

final class TrustProtectionBroadcaster
{
    public static function callbackChanged(Callback $callback, string $kind): void
    {
        $callback->loadMissing('transfer.senderWallet', 'transfer.receiverWallet');

        /** @var Transfer|null $transfer */
        $transfer = $callback->transfer;
        if ($transfer === null) {
            return;
        }

        $payload = [
            'callback_id' => $callback->id,
            'transfer_id' => $callback->transfer_id,
            'status' => $callback->status->value,
            'reference' => $callback->reference,
        ];

        foreach (self::partyUserIds($transfer) as $userId) {
            event(new TrustProtectionChanged($userId, $kind, $payload));
        }
    }

    /** @return list<string> */
    private static function partyUserIds(Transfer $transfer): array
    {
        $transfer->loadMissing('senderWallet.owner', 'receiverWallet.owner');

        $ids = [];

        foreach ([$transfer->senderWallet?->owner, $transfer->receiverWallet?->owner] as $owner) {
            if ($owner instanceof User) {
                $ids[$owner->getKey()] = true;
            }
        }

        return array_keys($ids);
    }
}
