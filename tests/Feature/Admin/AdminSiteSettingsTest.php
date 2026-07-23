<?php

declare(strict_types=1);

use App\Domain\Settings\Models\PlatformSetting;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Mail\PlatformTestMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function siteAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

function mailSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'notifications_enabled' => true,
        'mailer' => 'array',
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
    ], $overrides);
}

it('renders the admin site settings page', function () {
    $admin = siteAdmin();

    $this->actingAs($admin)->get('/admin/site')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Site')
            ->has('groups.mail')
            ->has('groups.seo')
            ->has('groups.security'));
});

it('stores mail settings encrypted and applies runtime config', function () {
    $admin = siteAdmin();

    $this->actingAs($admin)->put('/admin/site', array_merge(
        mailSettingsPayload(['mailer' => 'log', 'smtp_password' => 'secret-smtp-pass']),
        ['group' => 'mail'],
    ))->assertRedirect();

    $row = PlatformSetting::query()->find('mail');
    expect($row)->not->toBeNull()
        ->and($row->payload_encrypted)->not->toContain('secret-smtp-pass');

    app(PlatformSettingsService::class)->bustCache();
    app(PlatformSettingsService::class)->applyToConfig();

    expect(config('reton.mail.from_address'))->toBe('support@retonpay.com')
        ->and(config('reton.mail.notifications_enabled'))->toBeTrue();
});

it('stores smtp mail settings with validation feedback path', function () {
    $admin = siteAdmin();

    $this->actingAs($admin)->put('/admin/site', array_merge(
        mailSettingsPayload([
            'mailer' => 'smtp',
            'smtp_host' => 'smtp.mailgun.org',
            'smtp_username' => 'postmaster@retonpay.com',
            'smtp_password' => 'secret-smtp-pass',
        ]),
        ['group' => 'mail'],
    ))->assertRedirect()
        ->assertSessionHas('success');

    $decrypted = PlatformSetting::query()->find('mail')->decryptPayload();
    expect($decrypted['mailer'])->toBe('smtp')
        ->and($decrypted['smtp_host'])->toBe('smtp.mailgun.org')
        ->and($decrypted['smtp_password'])->toBe('secret-smtp-pass');
});

it('requires smtp host when mailer is smtp', function () {
    $admin = siteAdmin();

    $this->actingAs($admin)->put('/admin/site', array_merge(
        mailSettingsPayload(['mailer' => 'smtp', 'smtp_host' => '', 'smtp_username' => '']),
        ['group' => 'mail'],
    ))->assertSessionHasErrors(['smtp_host', 'smtp_username']);
});

it('sends a test email to the signed-in admin', function () {
    Mail::fake();

    $admin = siteAdmin();
    app(PlatformSettingsService::class)->updateGroup('mail', mailSettingsPayload(), $admin);

    $this->actingAs($admin)->post('/admin/site/test-mail')
        ->assertRedirect()
        ->assertSessionHas('success');

    Mail::assertSent(PlatformTestMail::class, fn (PlatformTestMail $mail) => $mail->hasTo($admin->email));
});

it('stores seo settings and exposes them in the page shell', function () {
    $admin = siteAdmin();

    $this->actingAs($admin)->put('/admin/site', [
        'group' => 'seo',
        'site_name' => 'Reton Pay',
        'title' => 'Reton Pay - trust-first wallet',
        'description' => 'Callback protection for African payments.',
        'keywords' => 'fintech, nigeria, wallet',
        'og_image' => '/og-banner.png',
        'twitter_site' => '@retonpay',
        'robots' => 'index,follow',
        'google_site_verification' => '',
        'locale' => 'en_NG',
    ])->assertRedirect();

    app(PlatformSettingsService::class)->bustCache();
    app(PlatformSettingsService::class)->applyToConfig();

    $this->actingAsGuest()->get('/about')
        ->assertOk()
        ->assertSee('Callback protection for African payments.', false)
        ->assertSee('og-banner.png', false)
        ->assertSee('og:image:secure_url', false)
        ->assertSee('image/png', false);
});

it('applies security headers on web responses', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('serves robots.txt from seo settings', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *')
        ->assertSee('Sitemap:');
});

it('exposes default png open graph image for social previews', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('/og-banner.png', false)
        ->assertSee('og:image:secure_url', false)
        ->assertSee('image/png', false)
        ->assertSee('apple-touch-icon.png', false);
});

it('forbids non-admins from site settings', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/admin/site')->assertForbidden();
});
