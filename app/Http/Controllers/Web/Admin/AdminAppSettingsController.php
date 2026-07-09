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
                'public_url' => (string) ($app['public_url'] ?: config('reton.links.public_base')),
                'admin_path' => AdminPath::current(),
            ],
            'kyc' => config('reton.kyc.tiers'),
            'reservedAdminPaths' => AdminPath::reserved(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'demo_enabled' => ['required', 'boolean'],
            'public_url' => ['nullable', 'url', 'max:255'],
            'admin_path' => ['required', 'string', 'max:48', new ValidAdminPath],
        ]);

        $previousPath = AdminPath::current();
        $validated['admin_path'] = AdminPath::normalize($validated['admin_path']);

        $this->settings->updateGroup('app', $validated, $request->user(), $request->ip());

        $message = 'Application settings saved.';
        if ($validated['admin_path'] !== $previousPath) {
            $message = 'Admin URL updated. Bookmark the new path — the old URL no longer works.';
        }

        return redirect(AdminPath::url('app-settings'))->with('success', $message);
    }
}
