@extends('mail.html.layout')

@section('content')
    <h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#0f1a14;letter-spacing:-0.02em;">
        Transaction {{ $directionLabel }}
    </h1>
    <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#3d5248;">
        Hi {{ $user->name }}, a <strong>{{ strtolower($directionLabel) }}</strong> just posted on your Reton wallet.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;border:1px solid #e3ece7;border-radius:12px;overflow:hidden;">
        <tr>
            <td colspan="2" style="padding:12px 16px;background:#0e7e5c;color:#ffffff;font-size:13px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;">
                Transaction details — {{ $directionLabel }}
            </td>
        </tr>
        <tr>
            <td style="padding:12px 16px;font-size:13px;color:#5c7368;border-bottom:1px solid #e3ece7;width:40%;">Account</td>
            <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f1a14;border-bottom:1px solid #e3ece7;font-family:ui-monospace,monospace;">{{ $maskedAccount }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;font-size:13px;color:#5c7368;border-bottom:1px solid #e3ece7;background:#fafcfb;">Account name</td>
            <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f1a14;border-bottom:1px solid #e3ece7;background:#fafcfb;">{{ $user->name }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;font-size:13px;color:#5c7368;border-bottom:1px solid #e3ece7;">Description</td>
            <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f1a14;border-bottom:1px solid #e3ece7;">{{ $description }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;font-size:13px;color:#5c7368;border-bottom:1px solid #e3ece7;background:#fafcfb;">Reference</td>
            <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f1a14;border-bottom:1px solid #e3ece7;background:#fafcfb;font-family:ui-monospace,monospace;">{{ $reference }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;font-size:13px;color:#5c7368;border-bottom:1px solid #e3ece7;">Amount</td>
            <td style="padding:12px 16px;font-size:16px;font-weight:700;color:#0e7e5c;border-bottom:1px solid #e3ece7;">{{ $amountFormatted }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;font-size:13px;color:#5c7368;border-bottom:1px solid #e3ece7;background:#fafcfb;">Date &amp; time</td>
            <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f1a14;border-bottom:1px solid #e3ece7;background:#fafcfb;">{{ $occurredAtFormatted }}</td>
        </tr>
        <tr>
            <td style="padding:12px 16px;font-size:13px;color:#5c7368;">Value date</td>
            <td style="padding:12px 16px;font-size:14px;font-weight:600;color:#0f1a14;">{{ $valueDateFormatted }}</td>
        </tr>
    </table>

    <p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#3d5248;">
        Current balance: <strong style="color:#0f1a14;">{{ $balanceFormatted }}</strong>
    </p>
    <p style="margin:0;font-size:12px;line-height:1.6;color:#5c7368;">
        This alert was sent by Reton. Manage email and SMS preferences in Profile. SMS alerts cost ₦{{ number_format(((int) config('reton.sms.alert_fee_minor', 600)) / 100, 2) }} each when enabled.
    </p>
@endsection
