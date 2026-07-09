Reset your Reton password

Hi {{ $user->name }},

We received a request to reset the password on your Reton wallet.

Reset your password:
{{ $resetUrl }}

This link expires in {{ (int) config('auth.passwords.users.expire', 60) }} minutes.

If you didn't request a password reset, you can safely ignore this email — your password won't change.

— Reton
