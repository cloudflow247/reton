<?php

declare(strict_types=1);

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Models\AdminAuditLog;
use App\Domain\Settings\Models\PlatformSetting;
use App\Models\User;
use App\Support\Admin\AdminPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Encrypted runtime configuration stored in the database.
 *
 * Secrets never touch git — admins set integration keys in the control panel.
 * Values are encrypted with APP_KEY and merged into Laravel config at boot.
 * Env vars remain the fallback until an admin saves a group in the dashboard.
 */
class PlatformSettingsService
{
    private const CACHE_KEY = 'platform_settings:merged';

    /** @var list<string> */
    private const SERVICE_GROUPS = [
        'alatpay',
        'paystack',
        'interswitch',
        'bridgecard',
        'giglogistics',
        'dojah',
        'remita',
        'termii',
    ];

    /** @var array<string, list<string>> */
    private const SECRET_FIELDS = [
        'alatpay' => ['api_key', 'merchant_password', 'business_bvn', 'webhook_secret'],
        'paystack' => ['secret_key', 'webhook_secret'],
        'interswitch' => ['client_id', 'client_secret'],
        'bridgecard' => ['access_token', 'secret_key'],
        'giglogistics' => ['api_key', 'webhook_secret'],
        'dojah' => ['app_id', 'secret_key'],
        'remita' => ['api_key', 'api_secret'],
        'termii' => ['api_key'],
        'app' => ['demo_password', 'demo_pin'],
        'mail' => ['smtp_password'],
    ];

    /** @var array<string, array<string, mixed>> */
    private const DEFAULTS = [
        'alatpay' => [
            // Live by default — fake must be chosen explicitly for local demos.
            'driver' => 'http',
            'base_url' => 'https://apibox.alatpay.ng',
            'api_key' => '',
            'merchant_email' => '',
            'merchant_password' => '',
            'business_id' => '',
            'business_bvn' => '',
            'webhook_secret' => '',
            'timeout' => 12,
        ],
        'paystack' => [
            'driver' => 'http',
            'base_url' => 'https://api.paystack.co',
            'secret_key' => '',
            'public_key' => '',
            'webhook_secret' => '',
            'timeout' => 15,
        ],
        'interswitch' => [
            'driver' => 'fake',
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
        'dojah' => [
            'driver' => 'fake',
            'base_url' => 'https://sandbox.dojah.io',
            'app_id' => '',
            'secret_key' => '',
            'timeout' => 20,
        ],
        'remita' => [
            'driver' => 'fake',
            'base_url' => 'https://api.remita.net',
            'merchant_id' => '',
            'api_key' => '',
            'api_secret' => '',
            'timeout' => 15,
        ],
        'termii' => [
            'driver' => 'fake',
            'base_url' => 'https://api.ng.termii.com',
            'api_key' => '',
            'sender_id' => 'Reton',
            'channel' => 'generic',
            'timeout' => 15,
        ],
        'app' => [
            'demo_enabled' => false,
            'demo_password' => 'demo1234',
            'demo_pin' => '1234',
            'public_url' => '',
            'admin_path' => 'admin',
            'listing_path' => '/l',
            'app_scheme' => 'reton',
            'ios_bundle_id' => 'ng.reton.app',
            'apple_team_id' => '',
            'android_package' => 'ng.reton.app',
            'android_sha256' => '',
        ],
        'pin' => [
            'max_attempts' => 5,
            'lockout_minutes' => 15,
        ],
        'callback' => [
            'hold_hours' => 72,
            'response_hours' => 24,
            'unanswered_resolution' => 'refund',
        ],
        'digital' => [
            'confirm_hours' => 48,
            'delivery_deadline_hours' => 72,
            'dispute_grace_hours' => 24,
        ],
        'physical' => [
            'ship_deadline_hours' => 48,
            'confirm_hours' => 72,
            'dispute_grace_hours' => 48,
            'verification_pass_score' => 70,
            'hub_verification_pass_score' => 80,
            'default_hub_name' => 'Giglogistics Verification Hub — Lekki',
            'default_hub_line1' => '12 Admiralty Way',
            'default_hub_city' => 'Lekki',
            'default_hub_state' => 'Lagos',
            'default_hub_phone' => '+234 700 GIG LOG',
        ],
        'recovery' => [
            'report_window_hours' => 48,
            'response_hours' => 48,
            'fee_bps' => 0,
        ],
        'kyc' => [
            'tier1_single_max' => 20_000_00,
            'tier1_daily_in_max' => 50_000_00,
            'tier1_balance_max' => 50_000_00,
            'tier2_single_max' => 100_000_00,
            'tier2_daily_in_max' => 100_000_00,
            'tier2_balance_max' => 100_000_00,
            'tier3_single_max' => 5_000_000_00,
            'tier3_daily_in_max' => 20_000_000_00,
            'tier3_balance_max' => 50_000_000_00,
        ],
        'cards' => [
            'provider' => 'bridgecard',
            'min_funding_ngn' => 1_000_00,
            'min_funding_usd' => 300,
            'default_usd_limit' => '500000',
        ],
        'fx' => [
            'usd_ngn_rate' => 1600.0,
            'spread_bps' => 150,
        ],
        'fraud' => [
            'velocity_window_minutes' => 10,
            'velocity_max_count' => 5,
            'velocity_points' => 40,
            'large_amount_threshold' => 5_000_000,
            'large_amount_points' => 45,
            'new_device_points' => 30,
            'failed_pin_threshold' => 3,
            'failed_pin_points' => 35,
            'new_beneficiary_points' => 15,
            'medium_min' => 40,
            'high_min' => 70,
            'escalate_min' => 90,
        ],
        'bills' => [
            'provider' => 'interswitch',
        ],
        'payouts' => [
            'provider' => 'paystack',
        ],
        'features' => [
            'withdraw' => true,
            'bills' => false,
            'cards' => false,
            'checkout' => false,
            'card_pay' => false,
            'one_time' => false,
            'physical_listings' => false,
        ],
        'fees' => [
            'transfer_instant_bps' => 0,
            'transfer_instant_flat_minor' => 0,
            'transfer_protected_bps' => 0,
            'transfer_protected_flat_minor' => 0,
            'withdraw_bps' => 0,
            'withdraw_flat_minor' => 0,
            'deposit_bps' => 0,
            'deposit_flat_minor' => 0,
            'callback_bps' => 0,
            'callback_flat_minor' => 0,
            'listing_publish_bps' => 0,
            'listing_publish_flat_minor' => 0,
            'marketplace_sale_bps' => 0,
            'marketplace_sale_flat_minor' => 0,
            'recovery_bps' => 0,
            'recovery_flat_minor' => 0,
            'sms_alert_bps' => 0,
            'sms_alert_flat_minor' => 600,
        ],
        'horizon' => [
            'allowed_emails' => '',
        ],
        'mail' => [
            'notifications_enabled' => true,
            'mailer' => 'log',
            'from_address' => 'support@retonpay.com',
            'from_name' => 'Reton',
            'support_address' => 'support@retonpay.com',
            'reply_to_address' => 'support@retonpay.com',
            'notify_on_support_ticket' => true,
            'notify_user_on_ticket' => true,
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
        ],
        'sms' => [
            'notifications_enabled' => false,
            'otp_enabled' => true,
            'whatsapp_otp_enabled' => false,
            'default_channel' => 'sms',
            'alert_fee_minor' => 600,
        ],
        'seo' => [
            'site_name' => 'Reton',
            'title' => 'Reton — payments you can take back',
            'description' => 'Reton is Africa\'s trust-first wallet with Callback Protection, wrong-transfer recovery, and real-time fraud checks — settled on ALAT by Wema.',
            'keywords' => 'fintech, nigeria, wallet, callback protection, wrong transfer recovery, ALATPay',
            'og_image' => '/og-banner.png',
            'twitter_site' => '@retonpay',
            'robots' => 'index,follow',
            'google_site_verification' => '',
            'locale' => 'en_NG',
        ],
        'security' => [
            'force_https' => false,
            'hsts_enabled' => true,
            'hsts_max_age' => 31536000,
            'frame_options' => 'DENY',
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'permissions_policy' => 'camera=(), microphone=(), geolocation=()',
            'csp_enabled' => true,
            'csp_report_only' => true,
            'session_secure_cookie' => false,
            'auth_rate_limit' => 10,
        ],
    ];

    /** @return list<string> */
    public function configurableGroups(): array
    {
        return array_keys(self::DEFAULTS);
    }

    public function applyToConfig(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        PlatformSetting::query()->each(function (PlatformSetting $row): void {
            try {
                $this->applyGroupToConfig($row->group, $row->decryptPayload());
            } catch (\Throwable) {
                // Corrupt row — skip rather than breaking boot.
            }
        });
    }

    /** @return array<string, array<string, mixed>> */
    public function mergedGroups(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function (): array {
            $merged = [];

            foreach (array_keys(self::DEFAULTS) as $group) {
                $merged[$group] = $this->effectiveGroup($group);
            }

            return $merged;
        });
    }

    /** @return array<string, mixed> */
    public function effectiveGroup(string $group): array
    {
        if (! isset(self::DEFAULTS[$group])) {
            throw new \InvalidArgumentException("Unknown settings group [{$group}].");
        }

        return array_merge(
            self::DEFAULTS[$group],
            $this->envSnapshot($group),
            $this->storedGroup($group),
        );
    }

    /** @return array<string, mixed> */
    public function maskedGroup(string $group): array
    {
        $values = $this->effectiveGroup($group);
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

    /** @return array<string, mixed> */
    public function runtimeGroup(string $group): array
    {
        return match ($group) {
            'kyc' => $this->flattenKycTiers(config('reton.kyc.tiers')),
            default => $this->maskedGroup($group),
        };
    }

    public function isIntegrationReady(string $group): bool
    {
        $values = $this->effectiveGroup($group);

        return match ($group) {
            'alatpay' => ($values['driver'] ?? '') === 'fake'
                || (
                    ! empty($values['merchant_email'])
                    && ! empty($values['merchant_password'])
                    && ! empty($values['business_id'])
                    && ! empty($values['business_bvn'])
                ),
            'paystack' => ($values['driver'] ?? '') === 'fake'
                || ! empty($values['secret_key']),
            'termii' => ($values['driver'] ?? '') === 'fake'
                || (! empty($values['api_key']) && ! empty($values['sender_id'])),
            'remita' => ($values['driver'] ?? '') === 'fake'
                || (! empty($values['api_key']) && ! empty($values['merchant_id'])),
            'interswitch' => ($values['driver'] ?? '') === 'fake'
                || (! empty($values['client_id']) && ! empty($values['client_secret']) && ! empty($values['terminal_id'])),
            'giglogistics' => ($values['driver'] ?? '') === 'fake'
                || ! empty($values['api_key']),
            'dojah' => ($values['driver'] ?? '') === 'fake'
                || (! empty($values['app_id']) && ! empty($values['secret_key'])),
            default => false,
        };
    }

    public function isRemitaReady(): bool
    {
        return $this->isIntegrationReady('remita');
    }

    public function isDojahReady(): bool
    {
        return $this->isIntegrationReady('dojah');
    }

    public function isBvnVerificationReady(): bool
    {
        if ((string) config('services.kyc.bvn_provider', 'alatpay') === 'dojah') {
            return $this->isDojahReady();
        }

        $values = $this->effectiveGroup('alatpay');

        return ($values['driver'] ?? '') === 'fake'
            || (
                ! empty($values['merchant_email'])
                && ! empty($values['merchant_password'])
                && ! empty($values['business_id'])
            );
    }

    public function bvnProviderLabel(): string
    {
        return (string) config('services.kyc.bvn_provider', 'alatpay') === 'dojah' ? 'Dojah' : 'ALATPay';
    }

    public function isTermiiReady(): bool
    {
        return $this->isIntegrationReady('termii');
    }

    public function isVirtualCardsReady(): bool
    {
        if ($this->effectiveGroup('bridgecard')['driver'] === 'fake') {
            return true;
        }

        $values = $this->effectiveGroup('bridgecard');

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

        $current = $this->effectiveGroup($group);
        $secrets = self::SECRET_FIELDS[$group] ?? [];

        foreach ($secrets as $field) {
            $incoming = $input[$field] ?? null;
            if ($incoming === null || $incoming === '' || str_contains((string) $incoming, '••••')) {
                $input[$field] = $current[$field] ?? '';
            }
            unset($input["{$field}_set"]);
        }

        foreach (['api_key', 'merchant_email', 'merchant_password', 'business_id', 'business_bvn', 'webhook_secret', 'base_url', 'secret_key', 'public_key'] as $trimField) {
            if (isset($input[$trimField]) && is_string($input[$trimField])) {
                $input[$trimField] = trim($input[$trimField]);
            }
        }

        $payload = array_merge(
            self::DEFAULTS[$group],
            array_intersect_key($input, self::DEFAULTS[$group]),
        );

        PlatformSetting::query()->updateOrCreate(
            ['group' => $group],
            [
                'payload_encrypted' => PlatformSetting::encryptPayload($payload),
                'updated_by' => $admin->getKey(),
            ],
        );

        $this->bustCache();
        $this->applyGroupToConfig($group, $payload);

        if ($group === 'alatpay') {
            $this->forgetAlatpayMerchantSession($payload);
        }

        $this->audit($admin, 'settings.updated', $group, [
            'fields' => array_keys(array_intersect_key($input, self::DEFAULTS[$group])),
        ], $ip);

        return $this->maskedGroup($group);
    }

    /**
     * Never run production/staging with driver=fake when merchant credentials exist —
     * that silently skips live collection-history polls and leaves VA deposits uncredited.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeAlatpayRuntime(array $values): array
    {
        $hasCreds = filled($values['merchant_email'] ?? null)
            && filled($values['merchant_password'] ?? null)
            && filled($values['business_id'] ?? null);

        if (
            ($values['driver'] ?? '') === 'fake'
            && $hasCreds
            && app()->environment(['production', 'staging'])
        ) {
            Log::warning('ALATPay driver forced from fake to http — live credentials present.');
            $values['driver'] = 'http';
        }

        $base = rtrim((string) ($values['base_url'] ?? ''), '/');

        if ($base === 'https://api.alatpay.ng' || $base === 'http://api.alatpay.ng') {
            $values['base_url'] = 'https://apibox.alatpay.ng';
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function forgetAlatpayMerchantSession(array $values): void
    {
        $email = strtolower(trim((string) ($values['merchant_email'] ?? '')));
        $businessId = trim((string) ($values['business_id'] ?? ''));

        if ($email === '' || $businessId === '') {
            return;
        }

        Cache::forget('alatpay:merchant_session:'.hash('sha256', $email.'|'.$businessId));
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

    /** @param  array<string, mixed>  $values */
    private function applyGroupToConfig(string $group, array $values): void
    {
        if ($group === 'alatpay') {
            $values = $this->normalizeAlatpayRuntime($values);
        }

        if (in_array($group, self::SERVICE_GROUPS, true)) {
            foreach ($values as $key => $value) {
                config(["services.{$group}.{$key}" => $value]);
            }

            return;
        }

        if ($group === 'app') {
            $this->applyAppConfig($values);

            return;
        }

        if ($group === 'mail') {
            $this->applyMailConfig($values);

            return;
        }

        match ($group) {
            'pin' => config([
                'reton.pin.max_attempts' => (int) ($values['max_attempts'] ?? 5),
                'reton.pin.lockout_minutes' => (int) ($values['lockout_minutes'] ?? 15),
            ]),
            'callback' => config([
                'reton.callback.hold_hours' => (int) ($values['hold_hours'] ?? 72),
                'reton.callback.response_hours' => (int) ($values['response_hours'] ?? 24),
                'reton.callback.unanswered_resolution' => (string) ($values['unanswered_resolution'] ?? 'refund'),
            ]),
            'digital' => config([
                'reton.digital.confirm_hours' => (int) ($values['confirm_hours'] ?? 48),
                'reton.digital.delivery_deadline_hours' => (int) ($values['delivery_deadline_hours'] ?? 72),
                'reton.digital.dispute_grace_hours' => (int) ($values['dispute_grace_hours'] ?? 24),
            ]),
            'physical' => config([
                'reton.physical.ship_deadline_hours' => (int) ($values['ship_deadline_hours'] ?? 48),
                'reton.physical.confirm_hours' => (int) ($values['confirm_hours'] ?? 72),
                'reton.physical.dispute_grace_hours' => (int) ($values['dispute_grace_hours'] ?? 48),
                'reton.physical.verification_pass_score' => (int) ($values['verification_pass_score'] ?? 70),
                'reton.physical.hub_verification_pass_score' => (int) ($values['hub_verification_pass_score'] ?? 80),
                'reton.physical.default_hub_name' => (string) ($values['default_hub_name'] ?? ''),
                'reton.physical.default_hub_address' => [
                    'line1' => (string) ($values['default_hub_line1'] ?? ''),
                    'city' => (string) ($values['default_hub_city'] ?? ''),
                    'state' => (string) ($values['default_hub_state'] ?? ''),
                    'phone' => (string) ($values['default_hub_phone'] ?? ''),
                ],
            ]),
            'recovery' => config([
                'reton.recovery.report_window_hours' => (int) ($values['report_window_hours'] ?? 48),
                'reton.recovery.response_hours' => (int) ($values['response_hours'] ?? 48),
                'reton.recovery.fee_bps' => (int) ($values['fee_bps'] ?? 0),
            ]),
            'kyc' => config(['reton.kyc.tiers' => $this->unflattenKycTiers($values)]),
            'cards' => config([
                'reton.cards.provider' => (string) ($values['provider'] ?? 'bridgecard'),
                'reton.cards.min_funding_minor.NGN' => (int) ($values['min_funding_ngn'] ?? 1_000_00),
                'reton.cards.min_funding_minor.USD' => (int) ($values['min_funding_usd'] ?? 300),
                'reton.cards.default_usd_limit' => (string) ($values['default_usd_limit'] ?? '500000'),
            ]),
            'fx' => config([
                'reton.fx.usd_ngn_rate' => (float) ($values['usd_ngn_rate'] ?? 1600),
                'reton.fx.spread_bps' => (int) ($values['spread_bps'] ?? 150),
            ]),
            'fraud' => config([
                'reton.fraud.velocity_window_minutes' => (int) ($values['velocity_window_minutes'] ?? 10),
                'reton.fraud.velocity_max_count' => (int) ($values['velocity_max_count'] ?? 5),
                'reton.fraud.velocity_points' => (int) ($values['velocity_points'] ?? 40),
                'reton.fraud.large_amount_threshold' => (int) ($values['large_amount_threshold'] ?? 5_000_000),
                'reton.fraud.large_amount_points' => (int) ($values['large_amount_points'] ?? 45),
                'reton.fraud.new_device_points' => (int) ($values['new_device_points'] ?? 30),
                'reton.fraud.failed_pin_threshold' => (int) ($values['failed_pin_threshold'] ?? 3),
                'reton.fraud.failed_pin_points' => (int) ($values['failed_pin_points'] ?? 35),
                'reton.fraud.new_beneficiary_points' => (int) ($values['new_beneficiary_points'] ?? 15),
                'reton.fraud.medium_min' => (int) ($values['medium_min'] ?? 40),
                'reton.fraud.high_min' => (int) ($values['high_min'] ?? 70),
                'reton.fraud.escalate_min' => (int) ($values['escalate_min'] ?? 90),
            ]),
            'bills' => config(['reton.bills.provider' => (string) ($values['provider'] ?? 'interswitch')]),
            'payouts' => config(['reton.payouts.provider' => (string) ($values['provider'] ?? 'paystack')]),
            'features' => config([
                'reton.features.withdraw' => filter_var($values['withdraw'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'reton.features.bills' => filter_var($values['bills'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'reton.features.cards' => filter_var($values['cards'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'reton.features.checkout' => filter_var($values['checkout'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'reton.features.card_pay' => filter_var($values['card_pay'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'reton.features.one_time' => filter_var($values['one_time'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'reton.features.physical_listings' => filter_var($values['physical_listings'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]),
            'fees' => $this->applyFeesConfig($values),
            'horizon' => config(['reton.horizon.allowed_emails' => (string) ($values['allowed_emails'] ?? '')]),
            'sms' => config(['reton.sms' => [
                'notifications_enabled' => (bool) ($values['notifications_enabled'] ?? false),
                'otp_enabled' => (bool) ($values['otp_enabled'] ?? true),
                'whatsapp_otp_enabled' => (bool) ($values['whatsapp_otp_enabled'] ?? false),
                'default_channel' => (string) ($values['default_channel'] ?? 'sms'),
                'alert_fee_minor' => (int) ($values['alert_fee_minor'] ?? config('reton.sms.alert_fee_minor', 600)),
            ]]),
            'seo' => config(['reton.seo' => [
                'site_name' => (string) ($values['site_name'] ?? 'Reton'),
                'title' => (string) ($values['title'] ?? 'Reton'),
                'description' => (string) ($values['description'] ?? ''),
                'keywords' => (string) ($values['keywords'] ?? ''),
                'og_image' => (string) ($values['og_image'] ?? '/og-banner.png'),
                'twitter_site' => (string) ($values['twitter_site'] ?? ''),
                'robots' => (string) ($values['robots'] ?? 'index,follow'),
                'google_site_verification' => (string) ($values['google_site_verification'] ?? ''),
                'locale' => (string) ($values['locale'] ?? 'en_NG'),
            ]]),
            'security' => config(['reton.security' => [
                'force_https' => (bool) ($values['force_https'] ?? false),
                'hsts_enabled' => (bool) ($values['hsts_enabled'] ?? true),
                'hsts_max_age' => (int) ($values['hsts_max_age'] ?? 31536000),
                'frame_options' => (string) ($values['frame_options'] ?? 'DENY'),
                'referrer_policy' => (string) ($values['referrer_policy'] ?? 'strict-origin-when-cross-origin'),
                'permissions_policy' => (string) ($values['permissions_policy'] ?? ''),
                'csp_enabled' => (bool) ($values['csp_enabled'] ?? true),
                'csp_report_only' => (bool) ($values['csp_report_only'] ?? true),
                'session_secure_cookie' => (bool) ($values['session_secure_cookie'] ?? false),
                'auth_rate_limit' => (int) ($values['auth_rate_limit'] ?? 10),
            ]]),
            default => null,
        };

        if ($group === 'security') {
            config(['session.secure' => (bool) config('reton.security.session_secure_cookie', false)]);
        }
    }

    /** @param  array<string, mixed>  $values */
    private function applyMailConfig(array $values): void
    {
        $mail = [
            'notifications_enabled' => (bool) ($values['notifications_enabled'] ?? true),
            'mailer' => (string) ($values['mailer'] ?? 'log'),
            'from_address' => (string) ($values['from_address'] ?? 'support@retonpay.com'),
            'from_name' => (string) ($values['from_name'] ?? 'Reton'),
            'support_address' => (string) ($values['support_address'] ?? 'support@retonpay.com'),
            'reply_to_address' => (string) ($values['reply_to_address'] ?? 'support@retonpay.com'),
            'notify_on_support_ticket' => (bool) ($values['notify_on_support_ticket'] ?? true),
            'notify_user_on_ticket' => (bool) ($values['notify_user_on_ticket'] ?? true),
            'smtp_host' => (string) ($values['smtp_host'] ?? ''),
            'smtp_port' => (int) ($values['smtp_port'] ?? 587),
            'smtp_username' => (string) ($values['smtp_username'] ?? ''),
            'smtp_password' => (string) ($values['smtp_password'] ?? ''),
            'smtp_encryption' => (string) ($values['smtp_encryption'] ?? 'tls'),
        ];

        config(['reton.mail' => $mail]);

        if ($mail['notifications_enabled']) {
            config(['mail.default' => $mail['mailer']]);
        }

        config([
            'mail.from.address' => $mail['from_address'],
            'mail.from.name' => $mail['from_name'],
        ]);

        if ($mail['mailer'] === 'smtp') {
            $encryption = (string) $mail['smtp_encryption'];
            config([
                'mail.mailers.smtp.host' => $mail['smtp_host'],
                'mail.mailers.smtp.port' => $mail['smtp_port'],
                'mail.mailers.smtp.username' => $mail['smtp_username'],
                'mail.mailers.smtp.password' => $mail['smtp_password'],
                'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            ]);
        }
    }

    /** @param  array<string, mixed>  $values */
    private function applyAppConfig(array $values): void
    {
        if (array_key_exists('demo_enabled', $values)) {
            config(['reton.demo.enabled' => (bool) $values['demo_enabled']]);
        }
        if (! empty($values['demo_password'])) {
            config(['reton.demo.password' => (string) $values['demo_password']]);
        }
        if (! empty($values['demo_pin'])) {
            config(['reton.demo.pin' => (string) $values['demo_pin']]);
        }
        if (! empty($values['public_url'])) {
            config(['reton.links.public_base' => rtrim((string) $values['public_url'], '/')]);
        }
        if (! empty($values['admin_path'])) {
            config(['reton.admin.path' => AdminPath::normalize((string) $values['admin_path'])]);
        }
        if (array_key_exists('listing_path', $values)) {
            config(['reton.links.listing_path' => (string) $values['listing_path']]);
        }
        if (array_key_exists('app_scheme', $values)) {
            config(['reton.links.app_scheme' => (string) $values['app_scheme']]);
        }
        if (array_key_exists('ios_bundle_id', $values)) {
            config(['reton.links.mobile.ios_bundle_id' => (string) $values['ios_bundle_id']]);
        }
        if (array_key_exists('apple_team_id', $values)) {
            config(['reton.links.mobile.apple_team_id' => (string) $values['apple_team_id']]);
        }
        if (array_key_exists('android_package', $values)) {
            config(['reton.links.mobile.android_package' => (string) $values['android_package']]);
        }
        if (array_key_exists('android_sha256', $values)) {
            config(['reton.links.mobile.android_sha256' => (string) $values['android_sha256']]);
        }
    }

    /** @return array<string, mixed> */
    private function envSnapshot(string $group): array
    {
        return match ($group) {
            'app' => [
                'demo_enabled' => (bool) config('reton.demo.enabled'),
                'demo_password' => (string) config('reton.demo.password'),
                'demo_pin' => (string) config('reton.demo.pin'),
                'public_url' => (string) config('reton.links.public_base'),
                'admin_path' => (string) config('reton.admin.path'),
                'listing_path' => (string) config('reton.links.listing_path'),
                'app_scheme' => (string) config('reton.links.app_scheme'),
                'ios_bundle_id' => (string) config('reton.links.mobile.ios_bundle_id'),
                'apple_team_id' => (string) config('reton.links.mobile.apple_team_id'),
                'android_package' => (string) config('reton.links.mobile.android_package'),
                'android_sha256' => (string) config('reton.links.mobile.android_sha256'),
            ],
            'pin' => [
                'max_attempts' => (int) config('reton.pin.max_attempts'),
                'lockout_minutes' => (int) config('reton.pin.lockout_minutes'),
            ],
            'callback' => [
                'hold_hours' => (int) config('reton.callback.hold_hours'),
                'response_hours' => (int) config('reton.callback.response_hours'),
                'unanswered_resolution' => (string) config('reton.callback.unanswered_resolution'),
            ],
            'digital' => [
                'confirm_hours' => (int) config('reton.digital.confirm_hours'),
                'delivery_deadline_hours' => (int) config('reton.digital.delivery_deadline_hours'),
                'dispute_grace_hours' => (int) config('reton.digital.dispute_grace_hours'),
            ],
            'physical' => [
                'ship_deadline_hours' => (int) config('reton.physical.ship_deadline_hours'),
                'confirm_hours' => (int) config('reton.physical.confirm_hours'),
                'dispute_grace_hours' => (int) config('reton.physical.dispute_grace_hours'),
                'verification_pass_score' => (int) config('reton.physical.verification_pass_score'),
                'hub_verification_pass_score' => (int) config('reton.physical.hub_verification_pass_score'),
                'default_hub_name' => (string) config('reton.physical.default_hub_name'),
                'default_hub_line1' => (string) config('reton.physical.default_hub_address.line1'),
                'default_hub_city' => (string) config('reton.physical.default_hub_address.city'),
                'default_hub_state' => (string) config('reton.physical.default_hub_address.state'),
                'default_hub_phone' => (string) config('reton.physical.default_hub_address.phone'),
            ],
            'recovery' => [
                'report_window_hours' => (int) config('reton.recovery.report_window_hours'),
                'response_hours' => (int) config('reton.recovery.response_hours'),
                'fee_bps' => (int) config('reton.recovery.fee_bps'),
            ],
            'kyc' => $this->flattenKycTiers(config('reton.kyc.tiers')),
            'cards' => [
                'provider' => (string) config('reton.cards.provider'),
                'min_funding_ngn' => (int) config('reton.cards.min_funding_minor.NGN'),
                'min_funding_usd' => (int) config('reton.cards.min_funding_minor.USD'),
                'default_usd_limit' => (string) config('reton.cards.default_usd_limit'),
            ],
            'fx' => [
                'usd_ngn_rate' => (float) config('reton.fx.usd_ngn_rate'),
                'spread_bps' => (int) config('reton.fx.spread_bps'),
            ],
            'fraud' => config('reton.fraud'),
            'bills' => [
                'provider' => (string) config('reton.bills.provider'),
            ],
            'payouts' => [
                'provider' => (string) config('reton.payouts.provider', 'paystack'),
            ],
            'features' => [
                'withdraw' => (bool) config('reton.features.withdraw', true),
                'bills' => (bool) config('reton.features.bills', false),
                'cards' => (bool) config('reton.features.cards', false),
                'checkout' => (bool) config('reton.features.checkout', false),
                'card_pay' => (bool) config('reton.features.card_pay', false),
                'one_time' => (bool) config('reton.features.one_time', false),
                'physical_listings' => (bool) config('reton.features.physical_listings', false),
            ],
            'fees' => (array) config('reton.fees', []),
            'horizon' => [
                'allowed_emails' => (string) config('reton.horizon.allowed_emails'),
            ],
            'mail' => (array) config('reton.mail'),
            'sms' => (array) config('reton.sms', []),
            'seo' => (array) config('reton.seo'),
            'security' => (array) config('reton.security'),
            default => in_array($group, self::SERVICE_GROUPS, true)
                ? (array) config("services.{$group}", [])
                : [],
        };
    }

    /**
     * @param  array<int|string, array<string, int>>  $tiers
     * @return array<string, int>
     */
    private function flattenKycTiers(array $tiers): array
    {
        $flat = [];

        foreach ([1, 2, 3] as $tier) {
            $limits = $tiers[$tier] ?? $tiers[(string) $tier] ?? [];
            $flat["tier{$tier}_single_max"] = (int) ($limits['single_transaction_max'] ?? 0);
            $flat["tier{$tier}_daily_in_max"] = (int) ($limits['daily_inflow_max'] ?? 0);
            $flat["tier{$tier}_balance_max"] = (int) ($limits['wallet_balance_max'] ?? 0);
        }

        return $flat;
    }

    /**
     * @param  array<string, int>  $flat
     * @return array<int, array<string, int>>
     */
    private function unflattenKycTiers(array $flat): array
    {
        $tiers = [];

        foreach ([1, 2, 3] as $tier) {
            $tiers[$tier] = [
                'single_transaction_max' => (int) ($flat["tier{$tier}_single_max"] ?? 0),
                'daily_inflow_max' => (int) ($flat["tier{$tier}_daily_in_max"] ?? 0),
                'wallet_balance_max' => (int) ($flat["tier{$tier}_balance_max"] ?? 0),
            ];
        }

        return $tiers;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function applyFeesConfig(array $values): void
    {
        $fees = [];

        foreach (array_keys(self::DEFAULTS['fees']) as $key) {
            $fees[$key] = max(0, (int) ($values[$key] ?? 0));
        }

        config(['reton.fees' => $fees]);

        // Keep legacy recovery + SMS fee keys in sync for existing call sites.
        config([
            'reton.recovery.fee_bps' => $fees['recovery_bps'],
            'reton.sms.alert_fee_minor' => $fees['sms_alert_flat_minor'],
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
