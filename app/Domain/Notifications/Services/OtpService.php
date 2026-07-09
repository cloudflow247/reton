<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpService
{
    private const TTL_SECONDS = 600;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly SmsNotificationService $sms) {}

    public function send(string $phone, string $purpose): void
    {
        $this->throttleSend($phone, $purpose);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey($phone, $purpose), [
            'code' => hash('sha256', $code),
            'attempts' => 0,
        ], self::TTL_SECONDS);

        $this->sms->sendOtp($phone, $code);
    }

    public function verify(string $phone, string $purpose, string $code): void
    {
        $key = $this->cacheKey($phone, $purpose);
        /** @var array{code: string, attempts: int}|null $stored */
        $stored = Cache::get($key);

        if ($stored === null) {
            throw ValidationException::withMessages([
                'otp' => ['This code has expired. Request a new one.'],
            ]);
        }

        if ($stored['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);
            throw ValidationException::withMessages([
                'otp' => ['Too many wrong attempts. Request a new code.'],
            ]);
        }

        if (! hash_equals($stored['code'], hash('sha256', $code))) {
            $stored['attempts']++;
            Cache::put($key, $stored, self::TTL_SECONDS);

            throw ValidationException::withMessages([
                'otp' => ['Invalid verification code.'],
            ]);
        }

        Cache::forget($key);
    }

    private function throttleSend(string $phone, string $purpose): void
    {
        $key = 'otp-send:'.hash('sha256', $phone.$purpose);

        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'phone' => ['Please wait before requesting another code.'],
            ]);
        }

        RateLimiter::hit($key, 300);
    }

    private function cacheKey(string $phone, string $purpose): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? $phone;

        return 'otp:'.hash('sha256', $digits.$purpose);
    }
}
