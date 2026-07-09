<?php

declare(strict_types=1);

namespace App\Domain\Cards\Bridgecard\Support;

/**
 * AES-256 encryption compatible with Bridgecard's AES-Anywhere pin requirement.
 *
 * @see https://docs.bridgecard.co/
 */
final class BridgecardPinCipher
{
    public static function encrypt(string $pin, string $secretKey): string
    {
        $salt = random_bytes(8);
        $salted = '';
        $dx = '';

        while (strlen($salted) < 48) {
            $dx = md5($dx.$secretKey.$salt, true);
            $salted .= $dx;
        }

        $key = substr($salted, 0, 32);
        $iv = substr($salted, 32, 16);
        $encrypted = openssl_encrypt($pin, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Could not encrypt card PIN for Bridgecard.');
        }

        return base64_encode('Salted__'.$salt.$encrypted);
    }
}
