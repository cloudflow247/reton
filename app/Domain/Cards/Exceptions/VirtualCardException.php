<?php

declare(strict_types=1);

namespace App\Domain\Cards\Exceptions;

final class VirtualCardException extends \RuntimeException
{
    public static function notReady(): self
    {
        return new self('Virtual cards are not configured yet. Ask an admin to add Bridgecard Issuing settings.');
    }

    public static function alreadyIssued(?string $currency = null): self
    {
        $label = $currency !== null ? strtoupper($currency).' ' : '';

        return new self("You already have an active Reton {$label}virtual card.");
    }

    public static function missingProfile(): self
    {
        return new self('Add a verified phone number and email on your profile before issuing a card.');
    }

    public static function providerFailed(string $operation, string $message = ''): self
    {
        $detail = $message !== '' ? ": {$message}" : '';

        return new self("Bridgecard virtual card {$operation} failed{$detail}");
    }
}
