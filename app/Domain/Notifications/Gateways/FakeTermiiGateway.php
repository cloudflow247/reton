<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Gateways;

use App\Domain\Notifications\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

final class FakeTermiiGateway implements SmsGateway
{
    public function send(string $to, string $message, string $channel = 'sms'): void
    {
        Log::info('termii.fake.send', [
            'to' => $to,
            'channel' => $channel,
            'message' => $message,
        ]);
    }

    public function ping(): bool
    {
        return true;
    }
}
