@if ($forSupport)
New support ticket escalated on Reton.

Reference: {{ $ticket->reference }}
Subject: {{ $ticket->subject }}
User: {{ $user->name }} <{{ $user->email }}>
Status: {{ $ticket->status->value }}

@if ($ticket->note)
Note:
{{ $ticket->note }}
@endif

Review in the admin dashboard.
@else
Hi {{ $user->name }},

We received your support request and assigned reference **{{ $ticket->reference }}**.

Subject: {{ $ticket->subject }}

Our team will respond within one business day at {{ $user->email }}.

— Reton Support
@endif
