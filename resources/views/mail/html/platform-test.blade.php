@extends('mail.html.layout')

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;font-weight:700;color:#0f1a14;">Email is working</h1>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#3d5248;">
        Hi {{ $admin->name }}, this confirms Reton platform email notifications are configured correctly.
    </p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7f5;border-radius:12px;">
        <tr>
            <td style="padding:16px;font-size:14px;line-height:1.6;color:#3d5248;">
                <strong>From:</strong> {{ config('reton.mail.from_address') }}<br>
                <strong>Mailer:</strong> {{ config('mail.default') }}
            </td>
        </tr>
    </table>
@endsection
