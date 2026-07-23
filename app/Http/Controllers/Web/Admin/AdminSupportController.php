<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Settings\Services\PlatformSettingsService;
use App\Domain\Support\Enums\SupportTicketStatus;
use App\Domain\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSupportController extends Controller
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->string('status', 'open');

        $query = SupportTicket::query()->with('user:id,name,email')->latest();

        if ($status === 'open') {
            $query->whereIn('status', [SupportTicketStatus::Open, SupportTicketStatus::Escalated]);
        } elseif ($status === 'resolved') {
            $query->where('status', SupportTicketStatus::Resolved);
        }

        $tickets = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Support', [
            'filters' => ['status' => $status],
            'tickets' => $tickets->through(fn (SupportTicket $ticket): array => [
                'id' => $ticket->id,
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'note' => $ticket->note,
                'status' => $ticket->status->value,
                'user' => $ticket->user ? [
                    'id' => $ticket->user->id,
                    'name' => $ticket->user->name,
                    'email' => $ticket->user->email,
                ] : null,
                'created_at' => $ticket->created_at?->toIso8601String(),
                'updated_at' => $ticket->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function resolve(Request $request, string $adminPrefix, SupportTicket $ticket): RedirectResponse
    {
        unset($adminPrefix);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $admin = $request->user();

        if (! $admin instanceof User) {
            abort(403);
        }

        $ticket->update([
            'status' => SupportTicketStatus::Resolved,
            'resolved_at' => now(),
            'metadata' => array_merge((array) $ticket->metadata, [
                'resolved_by' => $admin->getKey(),
                'admin_note' => $validated['note'] ?? null,
            ]),
        ]);

        $this->settings->audit(
            $admin,
            'support.resolved',
            'support',
            ['ticket_id' => $ticket->id, 'reference' => $ticket->reference],
            $request->ip(),
        );

        return back()->with('success', 'Ticket '.$ticket->reference.' marked resolved.');
    }
}
