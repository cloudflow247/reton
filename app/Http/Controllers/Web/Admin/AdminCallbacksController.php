<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Callback\Enums\CallbackResolution;
use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Callback\Models\Callback;
use App\Domain\Callback\Services\CallbackService;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminCallbacksController extends Controller
{
    public function __construct(
        private readonly CallbackService $callbacks,
        private readonly PlatformSettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->string('status', 'pending');

        $query = Callback::query()
            ->with(['transfer:id,reference,amount,currency,status', 'initiator:id,name,email'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $callbacks = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Callbacks', [
            'filters' => ['status' => $status],
            'callbacks' => $callbacks->through(fn (Callback $callback): array => [
                'id' => $callback->id,
                'reference' => $callback->reference,
                'status' => $callback->status->value,
                'reason' => $callback->reason,
                'responds_by' => $callback->responds_by?->toIso8601String(),
                'transfer' => $callback->transfer ? [
                    'id' => $callback->transfer->id,
                    'reference' => $callback->transfer->reference,
                    'amount' => $callback->transfer->amount,
                    'currency' => $callback->transfer->currency,
                ] : null,
                'initiator' => $callback->initiator ? [
                    'id' => $callback->initiator->id,
                    'name' => $callback->initiator->name,
                    'email' => $callback->initiator->email,
                ] : null,
                'created_at' => $callback->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function resolve(Request $request, string $adminPrefix, Callback $callback): RedirectResponse
    {
        unset($adminPrefix);

        $validated = $request->validate([
            'resolution' => ['required', 'in:release,refund'],
        ]);

        if ($callback->status !== CallbackStatus::Pending) {
            return back()->with('error', 'This callback is no longer pending.');
        }

        $this->callbacks->resolve(
            $callback,
            CallbackResolution::from($validated['resolution']),
            $request->user(),
        );

        $this->settings->audit(
            $request->user(),
            'callback.admin_resolved',
            'callback',
            ['callback_id' => $callback->id, 'resolution' => $validated['resolution']],
            $request->ip(),
        );

        return back()->with('success', 'Callback '.$callback->reference.' resolved.');
    }
}
