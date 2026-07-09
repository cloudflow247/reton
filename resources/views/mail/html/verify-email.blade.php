@extends('mail.html.layout')

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;font-weight:700;color:#0f1a14;letter-spacing:-0.02em;">
        Verify your email
    </h1>
    <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#3d5248;">
        Hi {{ $user->name }}, welcome to Reton. Confirm your email to secure your wallet and unlock transfers, bills, and Callback Protection.
    </p>
    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
        <tr>
            <td style="border-radius:12px;background:#0e7e5c;">
                <a href="{{ $verificationUrl }}" target="_blank" rel="noopener"
                   style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">
                    Verify email address
                </a>
            </td>
        </tr>
    </table>
    <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#5c7368;">
        This link expires in {{ (int) config('auth.verification.expire', 60) }} minutes. If the button doesn't work, copy and paste this URL into your browser:
    </p>
    <p style="margin:0;font-size:12px;line-height:1.5;word-break:break-all;color:#0e7e5c;">
        {{ $verificationUrl }}
    </p>
    <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#5c7368;">
        If you didn't create a Reton account, you can safely ignore this email.
    </p>
@endsection
