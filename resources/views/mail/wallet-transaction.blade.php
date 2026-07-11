Hi {{ $user->name }},

Reton {{ $directionLabel }} alert

Account: {{ $maskedAccount }}
Account name: {{ $user->name }}
Description: {{ $description }}
Reference: {{ $reference }}
Amount: {{ $amountFormatted }}
Date & time: {{ $occurredAtFormatted }}
Value date: {{ $valueDateFormatted }}

Current balance: {{ $balanceFormatted }}

Manage notification preferences in your Reton Profile.
SMS alerts cost ₦{{ number_format(((int) config('reton.sms.alert_fee_minor', 600)) / 100, 2) }} each when enabled.

— Reton
