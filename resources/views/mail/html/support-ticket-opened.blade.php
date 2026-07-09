@extends('mail.html.layout')

@section('content')
    @if ($forSupport)
        <h1 style="margin:0 0 12px;font-size:22px;font-weight:700;color:#0f1a14;">New support ticket</h1>
        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#3d5248;">
            A customer escalated to human support on Reton.
        </p>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px;background:#f4f7f5;border-radius:12px;">
            <tr>
                <td style="padding:16px;font-size:14px;line-height:1.6;color:#3d5248;">
                    <strong>Reference:</strong> {{ $ticket->reference }}<br>
                    <strong>Subject:</strong> {{ $ticket->subject }}<br>
                    <strong>User:</strong> {{ $user->name }} &lt;{{ $user->email }}&gt;<br>
                    <strong>Status:</strong> {{ $ticket->status->value }}
                    @if ($ticket->note)
                        <br><br><strong>Note:</strong><br>{{ $ticket->note }}
                    @endif
                </td>
            </tr>
        </table>
    @else
        <h1 style="margin:0 0 12px;font-size:22px;font-weight:700;color:#0f1a14;">We received your request</h1>
        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#3d5248;">
            Hi {{ $user->name }}, your support request is in our queue. Reference <strong>{{ $ticket->reference }}</strong>.
        </p>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px;background:#f4f7f5;border-radius:12px;">
            <tr>
                <td style="padding:16px;font-size:14px;line-height:1.6;color:#3d5248;">
                    <strong>Subject:</strong> {{ $ticket->subject }}<br>
                    Our team will respond within one business day at {{ $user->email }}.
                </td>
            </tr>
        </table>
    @endif
@endsection
