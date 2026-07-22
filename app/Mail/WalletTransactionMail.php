<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class WalletTransactionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $direction,
        public Money $amount,
        public Money $balance,
        public string $reference,
        public string $description,
        public Carbon $occurredAt,
        public string $walletAccountNumber = '',
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) config('reton.mail.from_address', 'support@retonpay.com');
        $fromName = (string) config('reton.mail.from_name', 'Reton');
        $label = $this->direction === 'credit' ? 'Credit' : 'Debit';

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: sprintf('Reton %s alert - %s', $label, $this->formatMoney($this->amount)),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.html.wallet-transaction',
            text: 'mail.wallet-transaction',
            with: [
                'directionLabel' => $this->direction === 'credit' ? 'Credit' : 'Debit',
                'amountFormatted' => $this->formatMoney($this->amount),
                'balanceFormatted' => $this->formatMoney($this->balance),
                'maskedAccount' => $this->maskAccount($this->walletAccountNumber),
                'occurredAtFormatted' => $this->occurredAt->timezone(config('app.timezone'))->format('d-m-Y H:i:s'),
                'valueDateFormatted' => $this->occurredAt->timezone(config('app.timezone'))->format('d-m-Y'),
            ],
        );
    }

    private function formatMoney(Money $money): string
    {
        $symbol = $money->currency === 'NGN' ? '₦' : $money->currency.' ';

        return $symbol.number_format($money->amount / 100, 2);
    }

    private function maskAccount(string $account): string
    {
        $digits = preg_replace('/\D+/', '', $account) ?? '';

        if (strlen($digits) < 4) {
            return $account !== '' ? $account : '-';
        }

        return substr($digits, 0, 4).str_repeat('*', max(0, strlen($digits) - 6)).substr($digits, -2);
    }
}
