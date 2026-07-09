<?php

declare(strict_types=1);

namespace App\Domain\Bills\Interswitch\Services;

use App\Domain\Bills\Remita\Exceptions\BillProviderException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * OAuth 2.0 client-credentials tokens for Quickteller VAS APIs.
 *
 * @see https://docs.interswitchgroup.com/docs/authentication
 */
class InterswitchTokenService
{
    private const CACHE_KEY = 'interswitch:oauth_token';

    public function bearerToken(): string
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $clientId = (string) config('services.interswitch.client_id');
        $secret = (string) config('services.interswitch.client_secret');

        if ($clientId === '' || $secret === '') {
            throw BillProviderException::requestFailed('oauth', 401);
        }

        $response = Http::asForm()
            ->timeout((int) config('services.interswitch.timeout', 15))
            ->withHeaders([
                'Authorization' => 'Basic '.base64_encode($clientId.':'.$secret),
            ])
            ->post((string) config('services.interswitch.passport_url'), [
                'grant_type' => 'client_credentials',
                'scope' => 'profile',
            ]);

        if (! $response->successful()) {
            throw BillProviderException::requestFailed('oauth', $response->status());
        }

        $token = (string) $response->json('access_token', '');
        $expiresIn = max(60, (int) $response->json('expires_in', 3600));

        if ($token === '') {
            throw BillProviderException::requestFailed('oauth', 502);
        }

        Cache::put(self::CACHE_KEY, $token, $expiresIn - 60);

        return $token;
    }

    public function bustCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
