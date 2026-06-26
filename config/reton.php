<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Supported currencies
    |--------------------------------------------------------------------------
    |
    | The ISO-4217 currencies Reton operates in. The first entry is treated as
    | the platform default. The system chart of accounts is materialised once
    | per currency listed here.
    |
    */

    'currencies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('RETON_CURRENCIES', 'NGN'))
    ))),

    'default_currency' => env('RETON_DEFAULT_CURRENCY', 'NGN'),

    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    |
    | When enabled (never in production), the sign-in screen surfaces ready-to-
    | use demo accounts so reviewers can try the app instantly. The accounts are
    | provisioned by DemoSeeder and all share the same demo password + PIN.
    |
    */

    'demo' => [
        'enabled' => (bool) env('RETON_DEMO_MODE', false),
        'password' => env('RETON_DEMO_PASSWORD', 'demo1234'),
        'pin' => env('RETON_DEMO_PIN', '1234'),
        'accounts' => [
            ['name' => 'Ada Obi', 'email' => 'ada@demo.reton.ng', 'phone' => '+2348000000001', 'fund' => 750_000_00],
            ['name' => 'Bola Ade', 'email' => 'bola@demo.reton.ng', 'phone' => '+2348000000002', 'fund' => 120_000_00],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction PIN
    |--------------------------------------------------------------------------
    |
    | Controls how the second-factor transaction PIN behaves: how many failed
    | verifications are tolerated before the PIN is temporarily locked, and for
    | how long the lockout lasts.
    |
    */

    'pin' => [
        'max_attempts' => (int) env('RETON_PIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('RETON_PIN_LOCKOUT_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback (protected transfer) protection
    |--------------------------------------------------------------------------
    |
    | How long funds from a protected transfer remain in escrow before the
    | window to initiate a callback closes (after which they auto-release to the
    | receiver). Tunable per regulatory and product requirements.
    |
    */

    'callback' => [
        'hold_hours' => (int) env('RETON_CALLBACK_HOLD_HOURS', 72),

        // How long the receiver has to respond to a raised callback.
        'response_hours' => (int) env('RETON_CALLBACK_RESPONSE_HOURS', 24),

        // Outcome when a callback expires unanswered: 'refund' (protect the
        // sender who raised it) or 'release' (protect the receiver).
        'unanswered_resolution' => env('RETON_CALLBACK_UNANSWERED_RESOLUTION', 'refund'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wrong-transfer recovery
    |--------------------------------------------------------------------------
    |
    | report_window_hours: how recent the original transfer must be for a
    | recovery to be eligible. response_hours: how long the receiver has to
    | return or dispute before the recovery escalates to an admin. fee_bps: the
    | recovery service fee, in basis points, deducted from a successful return.
    |
    */

    'recovery' => [
        'report_window_hours' => (int) env('RETON_RECOVERY_REPORT_WINDOW_HOURS', 48),
        'response_hours' => (int) env('RETON_RECOVERY_RESPONSE_HOURS', 48),
        'fee_bps' => (int) env('RETON_RECOVERY_FEE_BPS', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fraud scoring
    |--------------------------------------------------------------------------
    |
    | Per-signal point weights and the thresholds that map a 0-100 score to a
    | risk level and action. Defaults are deliberately lenient so ordinary
    | activity is allowed; tune per risk appetite. A future Go scorer can honour
    | the same knobs.
    |
    */

    'fraud' => [
        'velocity_window_minutes' => (int) env('RETON_FRAUD_VELOCITY_WINDOW_MINUTES', 10),
        'velocity_max_count' => (int) env('RETON_FRAUD_VELOCITY_MAX_COUNT', 5),
        'velocity_points' => (int) env('RETON_FRAUD_VELOCITY_POINTS', 40),

        // Amount (minor units) at or above which a transaction is "large".
        'large_amount_threshold' => (int) env('RETON_FRAUD_LARGE_AMOUNT_THRESHOLD', 5_000_000),
        'large_amount_points' => (int) env('RETON_FRAUD_LARGE_AMOUNT_POINTS', 45),

        'new_device_points' => (int) env('RETON_FRAUD_NEW_DEVICE_POINTS', 30),

        'failed_pin_threshold' => (int) env('RETON_FRAUD_FAILED_PIN_THRESHOLD', 3),
        'failed_pin_points' => (int) env('RETON_FRAUD_FAILED_PIN_POINTS', 35),

        'new_beneficiary_points' => (int) env('RETON_FRAUD_NEW_BENEFICIARY_POINTS', 15),

        // Score thresholds: [medium_min, high_min] and the top band that escalates.
        'medium_min' => (int) env('RETON_FRAUD_MEDIUM_MIN', 40),
        'high_min' => (int) env('RETON_FRAUD_HIGH_MIN', 70),
        'escalate_min' => (int) env('RETON_FRAUD_ESCALATE_MIN', 90),
    ],

];
