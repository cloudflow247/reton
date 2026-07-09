<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Gateways;

use App\Domain\Kyc\Contracts\KycVerificationGateway;
use App\Domain\Kyc\Data\BvnIdentity;
use App\Domain\Kyc\Data\NinIdentity;
use App\Domain\Kyc\Exceptions\KycVerificationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Live Dojah identity verification.
 *
 * @see https://docs.dojah.io/docs/nigeria/lookup-bvn
 */
class HttpDojahGateway implements KycVerificationGateway
{
    public function verifyBvn(string $bvn): BvnIdentity
    {
        $bvn = (string) preg_replace('/\D/', '', $bvn);

        $response = $this->client()->get('/api/v1/kyc/bvn/full', ['bvn' => $bvn]);

        if ($response->status() === 404 || $response->json('error') !== null) {
            throw KycVerificationException::notFound('BVN');
        }

        if (! $response->successful()) {
            Log::warning('dojah.bvn.failed', ['status' => $response->status()]);
            throw KycVerificationException::providerUnavailable();
        }

        $entity = (array) $response->json('entity', []);

        if ($entity === []) {
            throw KycVerificationException::notFound('BVN');
        }

        return new BvnIdentity(
            bvn: $bvn,
            firstName: (string) ($entity['first_name'] ?? ''),
            lastName: (string) ($entity['last_name'] ?? ''),
            middleName: isset($entity['middle_name']) ? (string) $entity['middle_name'] : null,
            dateOfBirth: $this->parseDate($entity['date_of_birth'] ?? null),
            phone: isset($entity['phone_number1']) ? (string) $entity['phone_number1'] : null,
        );
    }

    public function verifyNin(string $nin): NinIdentity
    {
        $nin = (string) preg_replace('/\D/', '', $nin);

        $response = $this->client()->get('/api/v1/kyc/nin', ['nin' => $nin]);

        if ($response->status() === 404 || $response->json('error') !== null) {
            throw KycVerificationException::notFound('NIN');
        }

        if (! $response->successful()) {
            Log::warning('dojah.nin.failed', ['status' => $response->status()]);
            throw KycVerificationException::providerUnavailable();
        }

        $entity = (array) $response->json('entity', $response->json('data', []));

        if ($entity === []) {
            throw KycVerificationException::notFound('NIN');
        }

        return new NinIdentity(
            nin: $nin,
            firstName: (string) ($entity['first_name'] ?? $entity['firstname'] ?? ''),
            lastName: (string) ($entity['last_name'] ?? $entity['surname'] ?? ''),
            middleName: isset($entity['middle_name']) ? (string) $entity['middle_name'] : null,
            dateOfBirth: $this->parseDate($entity['date_of_birth'] ?? $entity['birthdate'] ?? null),
            phone: isset($entity['phone_number']) ? (string) $entity['phone_number'] : null,
        );
    }

    private function client(): PendingRequest
    {
        $appId = (string) config('services.dojah.app_id');
        $secret = (string) config('services.dojah.secret_key');

        if ($appId === '' || $secret === '') {
            throw KycVerificationException::providerUnavailable();
        }

        return Http::baseUrl((string) config('services.dojah.base_url'))
            ->timeout((int) config('services.dojah.timeout', 20))
            ->withHeaders([
                'AppId' => $appId,
                'Authorization' => $secret,
            ])
            ->acceptJson();
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
