<?php

declare(strict_types=1);

namespace App\Support\Admin;

/**
 * Normalizes and validates the secret admin panel URL segment.
 */
final class AdminPath
{
    /** @var list<string> */
    private const RESERVED = [
        'login',
        'register',
        'dashboard',
        'send',
        'lookup',
        'transfers',
        'add-money',
        'deposits',
        'receive',
        'static-account',
        'bills',
        'activity',
        'profile',
        'cards',
        'pin',
        'marketplace',
        'protection',
        'callbacks',
        'recoveries',
        'logout',
        'api',
        'horizon',
        'up',
        'l',
        'security',
        'how-it-works',
        'business',
        'about',
        'faq',
        'contact',
        '.well-known',
    ];

    public static function normalize(?string $path): string
    {
        $path = strtolower(trim((string) $path, '/'));

        return $path === '' ? 'admin' : $path;
    }

    public static function current(): string
    {
        return self::normalize((string) config('reton.admin.path', 'admin'));
    }

    public static function isValid(string $path): bool
    {
        $path = self::normalize($path);

        if (strlen($path) < 3 || strlen($path) > 48) {
            return false;
        }

        if (! preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/', $path)) {
            return false;
        }

        return ! in_array($path, self::RESERVED, true);
    }

    /** @return list<string> */
    public static function reserved(): array
    {
        return self::RESERVED;
    }

    public static function url(string $suffix = ''): string
    {
        $base = '/'.self::current();

        if ($suffix === '') {
            return $base;
        }

        return $base.'/'.ltrim($suffix, '/');
    }
}
