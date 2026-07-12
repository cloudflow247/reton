<?php

declare(strict_types=1);

use App\Domain\Settings\Models\AdminAuditLog;
use App\Domain\Settings\Models\PlatformSetting;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Models\User;
use App\Support\Admin\AdminPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

function adminUser(): User
{
    return readyUser(['is_admin' => true]);
}

function setAdminPath(string $path): void
{
    app(PlatformSettingsService::class)->updateGroup('app', [
        'demo_enabled' => false,
        'demo_password' => 'demo1234',
        'demo_pin' => '1234',
        'public_url' => '',
        'admin_path' => $path,
        'listing_path' => '/l',
        'app_scheme' => 'reton',
        'ios_bundle_id' => 'ng.reton.app',
        'apple_team_id' => '',
        'android_package' => 'ng.reton.app',
        'android_sha256' => '',
    ], adminUser());
}

function appSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'demo_enabled' => false,
        'demo_password' => '',
        'demo_pin' => '',
        'public_url' => '',
        'admin_path' => 'admin',
        'listing_path' => '/l',
        'app_scheme' => 'reton',
        'ios_bundle_id' => 'ng.reton.app',
        'apple_team_id' => '',
        'android_package' => 'ng.reton.app',
        'android_sha256' => '',
    ], $overrides);
}

it('redirects administrators to the admin panel after login', function () {
    $admin = readyUser(['is_admin' => true, 'password' => bcrypt('password')]);

    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(AdminPath::url());

    $this->assertAuthenticatedAs($admin);
});

it('creates an admin user whose password works for web login', function () {
    $this->artisan('reton:admin', [
        'email' => 'ops@retonpay.com',
        '--create' => true,
        '--name' => 'Ops Admin',
        '--password' => 'AdminPass123!',
    ])->assertSuccessful();

    $this->post('/login', [
        'email' => 'ops@retonpay.com',
        'password' => 'AdminPass123!',
    ])->assertRedirect('/admin');

    $this->assertAuthenticated();
});

it('forbids non-admins from the admin panel', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->actingAs($user)->get('/admin/integrations')->assertForbidden();
});

it('renders the admin dashboard for platform administrators', function () {
    $admin = adminUser();

    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('stats')
            ->has('integrations')
            ->has('recentAudit'));
});

it('stores integration secrets encrypted and never returns raw values', function () {
    $admin = adminUser();
    $service = app(PlatformSettingsService::class);

    $this->actingAs($admin)->post('/admin/integrations/save', [
        'integration' => 'alatpay',
        'driver' => 'http',
        'base_url' => 'https://apibox.alatpay.ng',
        'api_key' => 'sk_live_super_secret_key_1234',
        'business_id' => 'biz-001',
        'business_bvn' => '22334455667',
        'webhook_secret' => 'whsec_test_value',
        'timeout' => 20,
    ])->assertRedirect();

    $row = PlatformSetting::query()->find('alatpay');
    expect($row)->not->toBeNull();

    $raw = $row->payload_encrypted;
    expect($raw)->not->toContain('sk_live_super_secret_key_1234')
        ->and($raw)->not->toContain('whsec_test_value');

    $decrypted = $row->decryptPayload();
    expect($decrypted['api_key'])->toBe('sk_live_super_secret_key_1234')
        ->and($decrypted['business_id'])->toBe('biz-001');

    $masked = $service->maskedGroup('alatpay');
    expect($masked['api_key'])->toBe('••••••••1234')
        ->and($masked['api_key_set'])->toBeTrue();

    $this->actingAs($admin)->get('/admin/integrations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('integrations.alatpay.api_key', '••••••••1234')
            ->where('integrations.alatpay.api_key_set', true));
});

it('preserves secrets when masked placeholders are submitted', function () {
    $admin = adminUser();
    $service = app(PlatformSettingsService::class);

    $service->updateGroup('alatpay', [
        'driver' => 'http',
        'base_url' => 'https://apibox.alatpay.ng',
        'api_key' => 'original_secret_key_9999',
        'business_id' => 'biz-001',
        'business_bvn' => '',
        'webhook_secret' => '',
        'timeout' => 15,
    ], $admin);

    $this->actingAs($admin)->post('/admin/integrations/save', [
        'integration' => 'alatpay',
        'driver' => 'http',
        'base_url' => 'https://apibox.alatpay.ng',
        'api_key' => '••••••••9999',
        'business_id' => 'biz-002',
        'business_bvn' => '',
        'webhook_secret' => '',
        'timeout' => 25,
    ])->assertRedirect();

    $decrypted = PlatformSetting::query()->find('alatpay')->decryptPayload();
    expect($decrypted['api_key'])->toBe('original_secret_key_9999')
        ->and($decrypted['business_id'])->toBe('biz-002')
        ->and($decrypted['timeout'])->toBe(25);
});

it('tests alatpay connection using the integration slug not the admin path', function () {
    $admin = adminUser();
    app(PlatformSettingsService::class)->updateGroup('alatpay', [
        'driver' => 'fake',
        'base_url' => 'https://apibox.alatpay.ng',
        'api_key' => 'test-key',
        'business_id' => 'biz-001',
        'business_bvn' => '22334455667',
        'webhook_secret' => '',
        'timeout' => 15,
    ], $admin);

    $this->actingAs($admin)->post('/admin/integrations/alatpay/test')
        ->assertRedirect()
        ->assertSessionHas('success')
        ->assertSessionMissing('error');
});

it('writes audit logs without secret values', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post('/admin/integrations/save', [
        'integration' => 'alatpay',
        'driver' => 'fake',
        'base_url' => 'https://apibox.alatpay.ng',
        'api_key' => 'audit_test_secret',
        'business_id' => 'biz-audit',
        'business_bvn' => '',
        'webhook_secret' => '',
        'timeout' => 15,
    ]);

    $log = AdminAuditLog::query()->latest('created_at')->first();
    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('settings.updated')
        ->and($log->group)->toBe('alatpay')
        ->and(json_encode($log->meta))->not->toContain('audit_test_secret');
});

it('promotes and revokes admins via artisan command', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->artisan('reton:admin', ['email' => $user->email])
        ->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();

    $this->artisan('reton:admin', ['email' => $user->email, '--revoke' => true])
        ->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeFalse();
});

it('merges database settings into runtime config', function () {
    PlatformSetting::query()->create([
        'group' => 'alatpay',
        'payload_encrypted' => Crypt::encryptString(json_encode([
            'driver' => 'http',
            'base_url' => 'https://apibox.alatpay.ng',
            'api_key' => 'runtime_key',
            'business_id' => 'runtime_biz',
            'business_bvn' => '',
            'webhook_secret' => '',
            'timeout' => 30,
        ])),
    ]);

    app(PlatformSettingsService::class)->bustCache();
    app(PlatformSettingsService::class)->applyToConfig();

    expect(config('services.alatpay.api_key'))->toBe('runtime_key')
        ->and(config('services.alatpay.business_id'))->toBe('runtime_biz')
        ->and(config('services.alatpay.timeout'))->toBe(30);
});

it('allows customizing the admin panel URL from app settings', function () {
    $admin = adminUser();

    $this->actingAs($admin)->put(AdminPath::url('app-settings'), appSettingsPayload([
        'admin_path' => 'reton-control-x7k9',
    ]))->assertRedirect('/reton-control-x7k9/app-settings');

    expect(AdminPath::current())->toBe('reton-control-x7k9');

    $this->actingAs($admin)->get('/reton-control-x7k9')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));

    $this->actingAs($admin)->get('/admin')->assertNotFound();
});

it('rejects reserved admin path segments', function () {
    $admin = adminUser();

    $this->actingAs($admin)->put(AdminPath::url('app-settings'), appSettingsPayload([
        'admin_path' => 'dashboard',
    ]))->assertSessionHasErrors('admin_path');
});

it('shares the admin path only with administrators', function () {
    $admin = adminUser();
    setAdminPath('secret-console');

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('adminPath', '/secret-console'));

    $user = readyUser(['is_admin' => false]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('adminPath', null));
});

it('does not override env config when no database row exists for a group', function () {
    config(['services.alatpay.driver' => 'fake']);

    app(PlatformSettingsService::class)->bustCache();
    app(PlatformSettingsService::class)->applyToConfig();

    expect(config('services.alatpay.driver'))->toBe('fake');
});

it('allows admins to update kyc tier limits from the platform panel', function () {
    $admin = adminUser();

    $this->actingAs($admin)->put('/admin/platform', [
        'group' => 'kyc',
        'tier1_single_max' => 50_000_00,
        'tier1_daily_in_max' => 200_000_00,
        'tier1_balance_max' => 50_000_00,
        'tier2_single_max' => 100_000_00,
        'tier2_daily_in_max' => 100_000_00,
        'tier2_balance_max' => 100_000_00,
        'tier3_single_max' => 5_000_000_00,
        'tier3_daily_in_max' => 20_000_000_00,
        'tier3_balance_max' => 50_000_000_00,
    ])->assertRedirect();

    expect(config('reton.kyc.tiers.1.single_transaction_max'))->toBe(50_000_00);

    $this->actingAs($admin)->get('/admin/platform')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Platform')
            ->has('groups.kyc'));
});

it('stores dojah credentials encrypted in admin settings', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post('/admin/integrations/save', [
        'integration' => 'dojah',
        'driver' => 'http',
        'base_url' => 'https://api.dojah.io',
        'app_id' => 'dojah_app_secret_id',
        'secret_key' => 'dojah_live_secret_key',
        'timeout' => 20,
    ])->assertRedirect();

    $row = PlatformSetting::query()->find('dojah');
    expect($row)->not->toBeNull()
        ->and($row->payload_encrypted)->not->toContain('dojah_live_secret_key');

    app(PlatformSettingsService::class)->bustCache();
    app(PlatformSettingsService::class)->applyToConfig();

    expect(config('services.dojah.secret_key'))->toBe('dojah_live_secret_key')
        ->and(app(PlatformSettingsService::class)->isDojahReady())->toBeTrue();
});

it('stores paystack credentials and marks withdrawals ready', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post('/admin/integrations/save', [
        'integration' => 'paystack',
        'driver' => 'fake',
        'base_url' => 'https://api.paystack.co',
        'secret_key' => 'sk_test_reton_secret_key',
        'public_key' => 'pk_test_reton',
        'webhook_secret' => 'whsec_reton',
        'timeout' => 15,
    ])->assertRedirect();

    $row = PlatformSetting::query()->find('paystack');
    expect($row)->not->toBeNull()
        ->and($row->payload_encrypted)->not->toContain('sk_test_reton_secret_key');

    app(PlatformSettingsService::class)->bustCache();
    app(PlatformSettingsService::class)->applyToConfig();

    expect(config('services.paystack.secret_key'))->toBe('sk_test_reton_secret_key')
        ->and(app(PlatformSettingsService::class)->isIntegrationReady('paystack'))->toBeTrue();

    $this->actingAs($admin)->get('/admin/integrations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Integrations')
            ->where('integrations.paystack.ready', true)
            ->where('webhookUrls.paystack', url('/api/v1/webhooks/paystack')));
});

it('allows admins to configure payout provider and feature flags', function () {
    $admin = adminUser();

    $this->actingAs($admin)->put('/admin/platform', [
        'group' => 'payouts',
        'provider' => 'paystack',
    ])->assertRedirect();

    expect(config('reton.payouts.provider'))->toBe('paystack');

    $this->actingAs($admin)->put('/admin/platform', [
        'group' => 'features',
        'withdraw' => true,
        'bills' => false,
        'cards' => false,
        'checkout' => false,
        'card_pay' => false,
        'one_time' => false,
        'physical_listings' => false,
    ])->assertRedirect();

    expect(config('reton.features.withdraw'))->toBeTrue()
        ->and(config('reton.features.bills'))->toBeFalse()
        ->and(config('reton.features.checkout'))->toBeFalse()
        ->and(config('reton.features.one_time'))->toBeFalse()
        ->and(config('reton.features.physical_listings'))->toBeFalse();

    $this->actingAs($admin)->get('/admin/platform')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Platform')
            ->has('groups.payouts')
            ->has('groups.features')
            ->where('groups.payouts.provider', 'paystack')
            ->where('groups.features.withdraw', true));
});
