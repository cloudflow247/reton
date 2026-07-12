<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Recovery\Enums\RecoveryResolution;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Recovery\Services\RecoveryService;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminRecoveriesController extends Controller
{
    public function __construct(
        private readonly RecoveryService $recoveries,
        private readonly PlatformSettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->string('status', 'held');

        $query = Recovery::query()
            ->with(['reporter:id,name,email', 'transfer:id,reference,amount,currency'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $recoveries = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Recoveries', [
            'filters' => ['status' => $status],
            'recoveries' => $recoveries->through(fn (Recovery $recovery): array => [
                'id' => $recovery->id,
                'reference' => $recovery->reference,
                'status' => $recovery->status->value,
                'reason' => $recovery->reason,
                'amount' => $recovery->amount,
                'fee' => $recovery->fee,
                'currency' => $recovery->currency,
                'expires_at' => $recovery->expires_at?->toIso8601String(),
                'reporter' => $recovery->reporter ? [
                    'id' => $recovery->reporter->id,
                    'name' => $recovery->reporter->name,
                    'email' => $recovery->reporter->email,
                ] : null,
                'transfer' => $recovery->transfer ? [
                    'id' => $recovery->transfer->id,
                    'reference' => $recovery->transfer->reference,
                ] : null,
                'created_at' => $recovery->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function resolve(Request $request, string $adminPrefix, Recovery $recovery): RedirectResponse
    {
        unset($adminPrefix);

        $validated = $request->validate([
            'resolution' => ['required', 'in:return,release'],
        ]);

        if (! $recovery->isOpen()) {
            return back()->with('error', 'This recovery is no longer open.');
        }

        $this->recoveries->resolve(
            $recovery,
            RecoveryResolution::from($validated['resolution']),
            $request->user(),
        );

        $this->settings->audit(
            $request->user(),
            'recovery.admin_resolved',
            'recovery',
            ['recovery_id' => $recovery->id, 'resolution' => $validated['resolution']],
            $request->ip(),
        );

        return back()->with('success', 'Recovery '.$recovery->reference.' resolved.');
    }
}
