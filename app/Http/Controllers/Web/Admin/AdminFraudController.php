<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Fraud\Models\FraudAlert;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFraudController extends Controller
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->string('status', 'open');

        $query = FraudAlert::query()->with('user:id,name,email')->latest();

        if (in_array($status, ['open', 'resolved'], true)) {
            $query->where('status', $status);
        }

        $alerts = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Fraud', [
            'filters' => ['status' => $status],
            'alerts' => $alerts->through(fn (FraudAlert $alert): array => [
                'id' => $alert->id,
                'score' => $alert->score,
                'level' => $alert->level?->value ?? (string) $alert->level,
                'action' => $alert->action_context,
                'recommended_action' => $alert->recommended_action?->value ?? (string) $alert->recommended_action,
                'status' => $alert->status,
                'amount' => $alert->amount,
                'currency' => $alert->currency,
                'signals' => $alert->signals,
                'user' => $alert->user ? [
                    'id' => $alert->user->id,
                    'name' => $alert->user->name,
                    'email' => $alert->user->email,
                ] : null,
                'created_at' => $alert->created_at?->toIso8601String(),
                'resolved_at' => $alert->resolved_at?->toIso8601String(),
            ]),
        ]);
    }

    public function resolve(Request $request, string $adminPrefix, FraudAlert $alert): RedirectResponse
    {
        unset($adminPrefix);

        $validated = $request->validate([
            'freeze_user' => ['sometimes', 'boolean'],
        ]);

        if ($alert->status === 'resolved') {
            return back()->with('success', 'Alert already resolved.');
        }

        $alert->update([
            'status' => 'resolved',
            'resolved_by' => $request->user()?->getKey(),
            'resolved_at' => now(),
        ]);

        $froze = false;
        if (($validated['freeze_user'] ?? false) && $alert->user_id) {
            $target = \App\Models\User::query()->find($alert->user_id);
            if ($target !== null && ! $target->is_admin && $target->status === 'active') {
                $target->update(['status' => 'frozen']);
                $froze = true;
            }
        }

        $this->settings->audit(
            $request->user(),
            'fraud.resolved',
            'fraud',
            ['alert_id' => $alert->id, 'score' => $alert->score, 'froze_user' => $froze],
            $request->ip(),
        );

        return back()->with('success', $froze
            ? 'Fraud alert resolved and user account frozen.'
            : 'Fraud alert resolved.');
    }
}
