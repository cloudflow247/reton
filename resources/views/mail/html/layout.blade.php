@php
    $siteName = (string) config('reton.seo.site_name', 'Reton');
    $logoUrl = rtrim((string) (config('reton.links.public_base') ?: config('app.url')), '/') . '/shield.svg';
    $supportEmail = (string) config('reton.mail.support_address', 'support@retonpay.com');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? $siteName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f7f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f1a14;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f7f5;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e3ece7;box-shadow:0 8px 24px rgba(9,79,57,0.08);">
                <tr>
                    <td style="padding:28px 32px 20px;background:linear-gradient(135deg,#0e7e5c 0%,#094f39 100%);">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="44" valign="middle">
                                    <img src="{{ $logoUrl }}" width="40" height="40" alt="{{ $siteName }}" style="display:block;border-radius:10px;">
                                </td>
                                <td valign="middle" style="padding-left:12px;">
                                    <span style="font-size:20px;font-weight:700;color:#ffffff;letter-spacing:-0.02em;">{{ $siteName }}</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px 28px;border-top:1px solid #e3ece7;background:#fafcfb;">
                        <p style="margin:0 0 8px;font-size:12px;line-height:1.5;color:#5c7368;">
                            {{ $siteName }} - Africa's trust-first wallet with Callback Protection.
                        </p>
                        <p style="margin:0;font-size:12px;line-height:1.5;color:#5c7368;">
                            Questions? Reply to this email or write to
                            <a href="mailto:{{ $supportEmail }}" style="color:#0e7e5c;text-decoration:none;">{{ $supportEmail }}</a>
                        </p>
                    </td>
                </tr>
            </table>
            <p style="margin:16px 0 0;font-size:11px;color:#8aa399;">&copy; {{ date('Y') }} {{ $siteName }}. Settled on ALAT by Wema.</p>
        </td>
    </tr>
</table>
</body>
</html>
