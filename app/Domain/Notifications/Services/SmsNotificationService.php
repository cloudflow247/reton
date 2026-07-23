<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

class SmsNotificationService
{
    public function __construct(private readonly SmsGateway $gateway) {}

    public function isEnabled(): bool
    {
        return (bool) config('reton.sms.notifications_enabled', false);
    }

    public function sendOtp(string $phone, string $code, ?string $channel = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $channel = $channel ?? $this->preferredChannel();
        $message = 'Your Reton verification code is '.$code.'. Valid for 10 minutes. Do not share this code.';

        $this->sendSafely($phone, $message, $channel);
    }

    public function sendAlert(string $phone, string $message): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->sendSafely($phone, $message, 'sms');
    }

    private function preferredChannel(): string
    {
        if ((bool) config('reton.sms.whatsapp_otp_enabled', false)) {
            return 'whatsapp';
        }

        return 'sms';
    }

    private function sendSafely(string $phone, string $message, string $channel): void
    {
        if ($phone === '') {
            return;
        }

        try {
            $this->gateway->send($phone, $message, $channel);
        } catch (\Throwable $e) {
            Log::warning('sms.delivery_failed', [
                'phone' => substr($phone, -4),
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
