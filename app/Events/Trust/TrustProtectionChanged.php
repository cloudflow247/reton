<?php

declare(strict_types=1);

namespace App\Events\Trust;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies a user that their trust-protection state changed (callback, recovery, etc.).
 * Consumed by the dashboard and protection hub via Laravel Echo.
 */
class TrustProtectionChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $kind,
        public readonly array $payload = [],
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'trust.protection.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'kind' => $this->kind,
            'payload' => $this->payload,
        ];
    }
}
