<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Settings\Services\PlatformSettingsService;
use App\Http\Controllers\Controller;
use App\Rules\ValidAdminPath;
use App\Support\Admin\AdminPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAppSettingsController extends Controller
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function index(): Response
    {
        $app = $this->settings->maskedGroup('app');

        return Inertia::render('Admin/AppSettings', [
            'app' => [
                'demo_enabled' => (bool) ($app['demo_enabled'] ?? config('reton.demo.enabled')),
                'demo_password' => (string) ($app['demo_password'] ?? ''),
                'demo_password_set' => (bool) ($app['demo_password_set'] ?? false),
                'demo_pin' => (string) ($app['demo_pin'] ?? ''),
                'demo_pin_set' => (bool) ($app['demo_pin_set'] ?? false),
                'public_url' => (string) ($app['public_url'] ?: config('reton.links.public_base')),
                'admin_path' => AdminPath::current(),
                'listing_path' => (string) ($app['listing_path'] ?? config('reton.links.listing_path')),
                'app_scheme' => (string) ($app['app_scheme'] ?? config('reton.links.app_scheme')),
                'ios_bundle_id' => (string) ($app['ios_bundle_id'] ?? config('reton.links.mobile.ios_bundle_id')),
                'apple_team_id' => (string) ($app['apple_team_id'] ?? config('reton.links.mobile.apple_team_id')),
                'android_package' => (string) ($app['android_package'] ?? config('reton.links.mobile.android_package')),
                'android_sha256' => (string) ($app['android_sha256'] ?? config('reton.links.mobile.android_sha256')),
            ],
            'reservedAdminPaths' => AdminPath::reserved(),
        ]);
    }

    public function update(Request $request, string $adminPrefix): RedirectResponse
    {
        unset($adminPrefix);

        $validated = $request->validate([
            'demo_enabled' => ['required', 'boolean'],
            'demo_password' => ['nullable', 'string', 'min:4', 'max:64'],
            'demo_pin' => ['nullable', 'string', 'digits:4'],
            'public_url' => ['nullable', 'url', 'max:255'],
            'admin_path' => ['required', 'string', 'max:48', new ValidAdminPath],
            'listing_path' => ['required', 'string', 'max:32', 'regex:/^\//'],
            'app_scheme' => ['required', 'string', 'max:32', 'regex:/^[a-z][a-z0-9+\-.]*$/'],
            'ios_bundle_id' => ['required', 'string', 'max:120'],
            'apple_team_id' => ['nullable', 'string', 'max:20'],
            'android_package' => ['required', 'string', 'max:120'],
            'android_sha256' => ['nullable', 'string', 'max:120'],
        ]);

        $previousPath = AdminPath::current();
        $validated['admin_path'] = AdminPath::normalize($validated['admin_path']);

        $this->settings->updateGroup('app', $validated, $request->user(), $request->ip());

        $message = 'Application settings saved.';
        if ($validated['admin_path'] !== $previousPath) {
            $message = 'Admin URL updated. Bookmark the new path - the old URL no longer works.';
        }

        return redirect(AdminPath::url('app-settings'))->with('success', $message);
    }
}
