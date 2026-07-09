<?php

declare(strict_types=1);

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Models\AdminAuditLog;
use App\Domain\Settings\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Encrypted runtime configuration stored in the database.
 *
 * Secrets never touch git — admins set integration keys in the control panel.
 * Values are encrypted with APP_KEY and merged into Laravel config at boot.
 */
class PlatformSettingsService
{
    private const CACHE_KEY = 'platform_settings:merged';

    /** @var array<string, list<string>> */
    private const SECRET_FIELDS = [
        'alatpay' => ['api_key', 'business_bvn', 'webhook_secret'],
        'interswitch' => ['client_id', 'client_secret'],
        'bridgecard' => ['access_token', 'secret_key'],
        'giglogistics' => ['api_key', 'webhook_secret'],
    ];

    /** @var array<string, array<string, mixed>> */
    private const DEFAULTS = [
        'alatpay' => [
            'driver' => 'http',
            'base_url' => 'https://apibox.alatpay.ng',
            'api_key' => '',
            'business_id' => '',
            'business_bvn' => '',
            'webhook_secret' => '',
            'timeout' => 15,
        ],
        'interswitch' => [
            'driver' => 'http',
            'passport_url' => 'https://passport.interswitchng.com/passport/oauth/token',
            'base_url' => 'https://interswitchng.com/quicktellerservice/api/v5',
            'terminal_id' => '',
            'client_id' => '',
            'client_secret' => '',
            'request_reference_prefix' => '1453',
            'timeout' => 15,
        ],
        'bridgecard' => [
            'driver' => 'fake',
            'base_url' => 'https://issuecards.api.bridgecard.co/v1/issuing/sandbox',
            'access_token' => '',
            'secret_key' => '',
            'timeout' => 20,
        ],
        'giglogistics' => [
            'driver' => 'fake',
            'base_url' => 'https://api.giglogistics.com',
            'api_key' => '',
            'webhook_secret' => '',
            'fake_advance_minutes' => 1,
        ],
        'app' => [
            'demo_enabled' => false,
            'public_url' => '',
            'admin_path' => 'admin',
        ],
    ];

    public function applyToConfig(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        foreach ($this->mergedGroups() as $group => $values) {
            if ($group === 'app') {
                if (isset($values['demo_enabled'])) {
                    config(['reton.demo.enabled' => (bool) $values['demo_enabled']]);
                }
                if (! empty($values['public_url'])) {
                    config(['reton.links.public_base' => rtrim((string) $values['public_url'], '/')]);
                }
                if (! empty($values['admin_path'])) {
                    config(['reton.admin.path' => \App\Support\Admin\AdminPath::normalize((string) $values['admin_path'])]);
                }

                continue;
            }

            foreach ($values as $key => $value) {
                config(["services.{$group}.{$key}" => $value]);
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    public function mergedGroups(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function (): array {
            $merged = [];

            foreach (self::DEFAULTS as $group => $defaults) {
                $merged[$group] = array_merge($defaults, $this->storedGroup($group));
            }

            return $merged;
        });
    }

    /** @return array<string, mixed> */
    public function maskedGroup(string $group): array
    {
        $values = $this->mergedGroups()[$group] ?? [];
        $secrets = self::SECRET_FIELDS[$group] ?? [];

        foreach ($secrets as $field) {
            if (! empty($values[$field])) {
                $values[$field] = $this->mask((string) $values[$field]);
                $values["{$field}_set"] = true;
            } else {
                $values[$field] = '';
                $values["{$field}_set"] = false;
            }
        }

        return $values;
    }

    public function isIntegrationReady(string $group): bool
    {
        $values = $this->mergedGroups()[$group] ?? [];

        return match ($group) {
            'alatpay' => ($values['driver'] ?? '') === 'fake'
                || (! empty($values['api_key']) && ! empty($values['business_id'])),
            'remita' => ($values['driver'] ?? '') === 'fake'
                || (! empty($values['api_key']) && ! empty($values['merchant_id'])),
            'interswitch' => ($values['driver'] ?? '') === 'fake'
                || (! empty($values['client_id']) && ! empty($values['client_secret']) && ! empty($values['terminal_id'])),
            'giglogistics' => ($values['driver'] ?? '') === 'fake'
                || ! empty($values['api_key']),
            default => false,
        };
    }

    public function isRemitaReady(): bool
    {
        if (config('services.remita.driver', 'fake') === 'fake') {
            return true;
        }

        return ! empty(config('services.remita.api_key'))
            && ! empty(config('services.remita.merchant_id'));
    }

    public function isVirtualCardsReady(): bool
    {
        if (config('services.bridgecard.driver', 'fake') === 'fake') {
            return true;
        }

        $values = $this->mergedGroups()['bridgecard'] ?? [];

        return ! empty($values['access_token'])
            && ! empty($values['secret_key'])
            && ! empty($values['base_url']);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function updateGroup(string $group, array $input, User $admin, ?string $ip = null): array
    {
        if (! isset(self::DEFAULTS[$group])) {
            throw new \InvalidArgumentException("Unknown settings group [{$group}].");
        }

        $current = $this->mergedGroups()[$group];
        $secrets = self::SECRET_FIELDS[$group] ?? [];

        foreach ($secrets as $field) {
            $incoming = $input[$field] ?? null;
            if ($incoming === null || $incoming === '' || str_contains((string) $incoming, '••••')) {
                $input[$field] = $current[$field] ?? '';
            }
            unset($input["{$field}_set"]);
        }

        $payload = array_merge(self::DEFAULTS[$group], array_intersect_key($input, self::DEFAULTS[$group]));

        PlatformSetting::query()->updateOrCreate(
            ['group' => $group],
            [
                'payload_encrypted' => PlatformSetting::encryptPayload($payload),
                'updated_by' => $admin->getKey(),
            ],
        );

        $this->bustCache();
        $this->applyToConfig();

        $this->audit($admin, 'settings.updated', $group, [
            'fields' => array_keys(array_intersect_key($input, self::DEFAULTS[$group])),
        ], $ip);

        return $this->maskedGroup($group);
    }

    public function bustCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @param  array<string, mixed>  $meta */
    public function audit(User $admin, string $action, ?string $group, array $meta = [], ?string $ip = null): void
    {
        if (! Schema::hasTable('admin_audit_logs')) {
            return;
        }

        AdminAuditLog::create([
            'user_id' => $admin->getKey(),
            'action' => $action,
            'group' => $group,
            'meta' => $meta,
            'ip_address' => $ip,
            'created_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function storedGroup(string $group): array
    {
        $row = PlatformSetting::query()->find($group);

        if ($row === null) {
            return [];
        }

        try {
            return $row->decryptPayload();
        } catch (\Throwable) {
            return [];
        }
    }

    private function mask(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $visible = Str::substr($value, -4);

        return '••••••••'.$visible;
    }
}
