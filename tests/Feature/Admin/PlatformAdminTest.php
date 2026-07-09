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
    return User::factory()->create(['is_admin' => true]);
}

function setAdminPath(string $path): void
{
    app(PlatformSettingsService::class)->updateGroup('app', [
        'demo_enabled' => false,
        'public_url' => '',
        'admin_path' => $path,
    ], adminUser());
}

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

    $this->actingAs($admin)->put(AdminPath::url('app-settings'), [
        'demo_enabled' => false,
        'public_url' => '',
        'admin_path' => 'reton-control-x7k9',
    ])->assertRedirect('/reton-control-x7k9/app-settings');

    expect(AdminPath::current())->toBe('reton-control-x7k9');

    $this->actingAs($admin)->get('/reton-control-x7k9')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));

    $this->actingAs($admin)->get('/admin')->assertNotFound();
});

it('rejects reserved admin path segments', function () {
    $admin = adminUser();

    $this->actingAs($admin)->put(AdminPath::url('app-settings'), [
        'demo_enabled' => false,
        'public_url' => '',
        'admin_path' => 'dashboard',
    ])->assertSessionHasErrors('admin_path');
});

it('shares the admin path only with administrators', function () {
    $admin = adminUser();
    setAdminPath('secret-console');

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('adminPath', '/secret-console'));

    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('adminPath', null));
});
