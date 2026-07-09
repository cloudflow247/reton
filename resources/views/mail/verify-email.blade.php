Hi {{ $user->name }},

Welcome to Reton — verify your email to secure your wallet.

Verify your email:
{{ $verificationUrl }}

This link expires in {{ (int) config('auth.verification.expire', 60) }} minutes.

If you didn't create a Reton account, ignore this message.

— Reton Support
