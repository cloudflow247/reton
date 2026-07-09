<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlatformTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $admin) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) config('reton.mail.from_address', 'support@retonpay.com');
        $fromName = (string) config('reton.mail.from_name', 'Reton');

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: 'Reton email notifications are working',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.html.platform-test',
            text: 'mail.platform-test',
        );
    }
}
