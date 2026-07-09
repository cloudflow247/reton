<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Support\Services\SupportChatService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Support\EscalateSupportTicketRequest;
use App\Http\Requests\Web\Support\StoreSupportMessageRequest;
use App\Http\Resources\Api\V1\SupportMessageResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function __construct(private readonly SupportChatService $chat) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $messages = $this->chat->history($user);
        $openTickets = $this->chat->openTickets($user);

        return Inertia::render('Support', [
            'messages' => SupportMessageResource::collection($messages),
            'openTickets' => $openTickets->map(fn ($ticket) => [
                'id' => $ticket->id,
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'created_at' => $ticket->created_at?->toIso8601String(),
            ]),
            'welcome' => $this->chat->welcomeReply($user)->body,
            'quickPrompts' => [
                ['label' => 'Find a transaction', 'message' => 'Help me find a transaction'],
                ['label' => 'Callback protection', 'message' => 'How does callback protection work?'],
                ['label' => 'Wrong transfer', 'message' => 'I sent money to the wrong person'],
                ['label' => 'My trust score', 'message' => 'What is my trust score?'],
            ],
        ]);
    }

    public function storeMessage(StoreSupportMessageRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->chat->send($user, $request->string('message')->toString());

        return back();
    }

    public function escalate(EscalateSupportTicketRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ticket = $this->chat->escalate(
            $user,
            $request->string('subject')->toString(),
            $request->string('note')->toString() ?: null,
            $request->string('transfer_reference')->toString() ?: null,
        );

        return back()->with('support_ticket', $ticket->reference);
    }
}
