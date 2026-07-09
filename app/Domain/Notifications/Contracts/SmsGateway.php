<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

interface SmsGateway
{
    /**
     * @param  non-empty-string  $to  E.164-ish MSISDN (e.g. 2348012345678)
     */
    public function send(string $to, string $message, string $channel = 'sms'): void;

    public function ping(): bool;
}
