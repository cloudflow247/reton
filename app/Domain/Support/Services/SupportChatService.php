<?php

declare(strict_types=1);

namespace App\Domain\Support\Services;

use App\Domain\Notifications\Services\PlatformMailService;
use App\Domain\Support\Data\SupportReply;
use App\Domain\Support\Enums\SupportMessageRole;
use App\Domain\Support\Enums\SupportTicketStatus;
use App\Domain\Support\Models\SupportMessage;
use App\Domain\Support\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportChatService
{
    public function __construct(
        private readonly RuleBasedSupportResponder $responder,
        private readonly TransactionLookupService $lookup,
        private readonly PlatformMailService $mail,
    ) {}

    /**
     * @return Collection<int, SupportMessage>
     */
    public function history(User $user, int $limit = 50): Collection
    {
        return SupportMessage::query()
            ->where('user_id', $user->id)
            ->whereNull('ticket_id')
            ->oldest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{user: SupportMessage, assistant: SupportMessage}
     */
    public function send(User $user, string $message): array
    {
        return DB::transaction(function () use ($user, $message): array {
            $userMessage = SupportMessage::query()->create([
                'user_id' => $user->id,
                'role' => SupportMessageRole::User,
                'body' => trim($message),
            ]);

            $reply = $this->responder->respond($user, $message);

            $assistantMessage = SupportMessage::query()->create([
                'user_id' => $user->id,
                'role' => SupportMessageRole::Assistant,
                'body' => $reply->body,
                'actions' => $reply->actions,
                'metadata' => $reply->metadata,
            ]);

            return [
                'user' => $userMessage,
                'assistant' => $assistantMessage,
            ];
        });
    }

    public function escalate(User $user, string $subject, ?string $note = null, ?string $transferReference = null): SupportTicket
    {
        return DB::transaction(function () use ($user, $subject, $note, $transferReference): SupportTicket {
            $transferId = null;

            if ($transferReference !== null && $transferReference !== '') {
                $lookup = $this->lookup->lookup($user, $transferReference);
                $transferId = $lookup?->relatedId;
            }

            $ticket = SupportTicket::query()->create([
                'reference' => 'TKT-'.Str::upper((string) Str::ulid()),
                'user_id' => $user->id,
                'subject' => $subject,
                'status' => SupportTicketStatus::Escalated,
                'transfer_id' => $transferId,
                'note' => $note,
                'metadata' => [
                    'escalated_at' => now()->toIso8601String(),
                    'transfer_reference' => $transferReference,
                ],
            ]);

            $body = "Support ticket **{$ticket->reference}** opened. Our team will review your case and respond at **{$user->email}** within one business day.";

            if ($note) {
                $body .= "\n\nYour note: {$note}";
            }

            SupportMessage::query()->create([
                'user_id' => $user->id,
                'ticket_id' => $ticket->id,
                'role' => SupportMessageRole::User,
                'body' => $note ?: $subject,
            ]);

            SupportMessage::query()->create([
                'user_id' => $user->id,
                'ticket_id' => $ticket->id,
                'role' => SupportMessageRole::Assistant,
                'body' => $body,
                'actions' => [
                    ['label' => 'Protection center', 'href' => '/protection'],
                    ['label' => 'Activity', 'href' => '/activity'],
                ],
                'metadata' => ['ticket_id' => $ticket->id, 'ticket_reference' => $ticket->reference],
            ]);

            $this->mail->notifySupportTicketOpened($ticket, $user);

            return $ticket;
        });
    }

    /**
     * @return Collection<int, SupportTicket>
     */
    public function openTickets(User $user): Collection
    {
        return SupportTicket::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [SupportTicketStatus::Open, SupportTicketStatus::Escalated])
            ->latest('created_at')
            ->get();
    }

    public function welcomeReply(User $user): SupportReply
    {
        return $this->responder->respond($user, 'hello');
    }
}
