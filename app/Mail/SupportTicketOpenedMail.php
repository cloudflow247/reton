<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Support\Models\SupportTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketOpenedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public User $user,
        public bool $forSupport = false,
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) config('reton.mail.from_address', 'support@retonpay.com');
        $fromName = (string) config('reton.mail.from_name', 'Reton');
        $replyTo = (string) config('reton.mail.reply_to_address', $fromAddress);

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            replyTo: [new Address($replyTo, $fromName)],
            subject: $this->forSupport
                ? "[Reton Support] {$this->ticket->reference} - {$this->ticket->subject}"
                : "We received your support request - {$this->ticket->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.html.support-ticket-opened',
            text: 'mail.support-ticket-opened',
        );
    }
}
