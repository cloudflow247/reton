<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Notifications\Services\PlatformMailService;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSiteSettingsController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly PlatformMailService $mail,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Site', [
            'groups' => [
                'mail' => $this->settings->maskedGroup('mail'),
                'sms' => $this->settings->maskedGroup('sms'),
                'seo' => $this->settings->maskedGroup('seo'),
                'security' => $this->settings->maskedGroup('security'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'group' => ['required', 'in:mail,sms,seo,security'],
        ]);

        $group = (string) $request->input('group');

        $rules = match ($group) {
            'mail' => [
                'notifications_enabled' => ['required', 'boolean'],
                'mailer' => ['required', 'in:log,smtp,array'],
                'from_address' => ['required', 'email', 'max:255'],
                'from_name' => ['required', 'string', 'max:120'],
                'support_address' => ['required', 'email', 'max:255'],
                'reply_to_address' => ['required', 'email', 'max:255'],
                'notify_on_support_ticket' => ['required', 'boolean'],
                'notify_user_on_ticket' => ['required', 'boolean'],
                'smtp_host' => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
                'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
                'smtp_username' => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
                'smtp_password' => ['nullable', 'string', 'max:500'],
                'smtp_encryption' => ['required', 'in:tls,ssl,none'],
            ],
            'sms' => [
                'notifications_enabled' => ['required', 'boolean'],
                'otp_enabled' => ['required', 'boolean'],
                'whatsapp_otp_enabled' => ['required', 'boolean'],
                'default_channel' => ['required', 'in:sms,whatsapp'],
            ],
            'seo' => [
                'site_name' => ['required', 'string', 'max:120'],
                'title' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string', 'max:500'],
                'keywords' => ['nullable', 'string', 'max:500'],
                'og_image' => ['required', 'string', 'max:255'],
                'twitter_site' => ['nullable', 'string', 'max:64'],
                'robots' => ['required', 'string', 'max:64'],
                'google_site_verification' => ['nullable', 'string', 'max:120'],
                'locale' => ['required', 'string', 'max:16'],
            ],
            'security' => [
                'force_https' => ['required', 'boolean'],
                'hsts_enabled' => ['required', 'boolean'],
                'hsts_max_age' => ['required', 'integer', 'min:0', 'max:63072000'],
                'frame_options' => ['required', 'in:DENY,SAMEORIGIN'],
                'referrer_policy' => ['required', 'string', 'max:64'],
                'permissions_policy' => ['nullable', 'string', 'max:500'],
                'csp_enabled' => ['required', 'boolean'],
                'csp_report_only' => ['required', 'boolean'],
                'session_secure_cookie' => ['required', 'boolean'],
                'auth_rate_limit' => ['required', 'integer', 'min:3', 'max:60'],
            ],
        };

        $validated = $request->validate(array_merge(
            ['group' => ['required', 'in:mail,sms,seo,security']],
            $rules,
        ));

        unset($validated['group']);

        $this->settings->updateGroup($group, $validated, $request->user(), $request->ip());

        return back()->with('success', ucfirst($group).' settings saved.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        try {
            $this->mail->sendTestEmail($request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'Test email failed: '.$e->getMessage());
        }

        return back()->with('success', 'Test email sent to '.$request->user()->email.'.');
    }
}
