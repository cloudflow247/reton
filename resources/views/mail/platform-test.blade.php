Hi {{ $admin->name }},

This is a test message from Reton confirming that platform email notifications are configured correctly.

From: {{ config('reton.mail.from_address') }}
Mailer: {{ config('mail.default') }}

- Reton
