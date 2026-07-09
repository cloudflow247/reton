<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Domain\Support\Models\SupportTicket;
use App\Mail\PlatformTestMail;
use App\Mail\SupportTicketOpenedMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PlatformMailService
{
    public function isEnabled(): bool
    {
        return (bool) config('reton.mail.notifications_enabled', false);
    }

    public function notifySupportTicketOpened(SupportTicket $ticket, User $user): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $supportAddress = (string) config('reton.mail.support_address', 'support@retonpay.com');

        if ((bool) config('reton.mail.notify_on_support_ticket', true) && $supportAddress !== '') {
            $this->sendSafely($supportAddress, new SupportTicketOpenedMail($ticket, $user, forSupport: true));
        }

        if ((bool) config('reton.mail.notify_user_on_ticket', true) && $user->email) {
            $this->sendSafely($user->email, new SupportTicketOpenedMail($ticket, $user, forSupport: false));
        }
    }

    public function sendTestEmail(User $recipient): void
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('Email notifications are disabled in Site settings.');
        }

        Mail::to($recipient->email)->send(new PlatformTestMail($recipient));
    }

    private function sendSafely(string $to, object $mailable): void
    {
        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            Log::warning('Platform mail delivery failed', [
                'to' => $to,
                'mailable' => $mailable::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
