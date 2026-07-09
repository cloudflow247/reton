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
    | Admin panel URL
    |--------------------------------------------------------------------------
    |
    | First path segment for the platform admin (e.g. /your-secret-path). Override
    | via the admin dashboard or RETON_ADMIN_PATH; stored settings take precedence.
    |
    */

    'admin' => [
        'path' => env('RETON_ADMIN_PATH', 'admin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bill payments (Interswitch Quickteller VAS)
    |--------------------------------------------------------------------------
    |
    | Payment codes are Interswitch Quickteller identifiers per biller. Update
    | these from your merchant dashboard for production — QA sandbox often uses
    | 10903 for mobile recharge test scenarios.
    |
    | @see https://docs.interswitchgroup.com/docs/bills-payment-1
    */

    'bills' => [
        'provider' => env('RETON_BILL_PROVIDER', 'interswitch'),
        'payment_codes' => [
            'mtn' => ['airtime' => '628051043', 'data' => '10903', 'default' => '628051043'],
            'glo' => ['airtime' => '6280510420', 'data' => '10906', 'default' => '6280510420'],
            'airtel' => ['airtime' => '6280510425', 'data' => '10904', 'default' => '6280510425'],
            't2' => ['airtime' => '6280510426', 'data' => '10905', 'default' => '6280510426'],
            '9mobile' => ['airtime' => '6280510426', 'data' => '10905', 'default' => '6280510426'],
            'dstv' => ['cable_tv' => '051758901', 'default' => '051758901'],
            'gotv' => ['cable_tv' => '051758902', 'default' => '051758902'],
            'startimes' => ['cable_tv' => '051758903', 'default' => '051758903'],
            'showmax' => ['cable_tv' => '051758904', 'default' => '051758904'],
            'ikedc' => ['electricity' => '628051043', 'default' => '628051043'],
            'ekedc' => ['electricity' => '628051043', 'default' => '628051043'],
            'ibedc' => ['electricity' => '628051043', 'default' => '628051043'],
            'aedc' => ['electricity' => '628051043', 'default' => '628051043'],
            'phed' => ['electricity' => '628051043', 'default' => '628051043'],
            'kedco' => ['electricity' => '628051043', 'default' => '628051043'],
            'sportybet' => ['betting' => '10907', 'default' => '10907'],
            'bet9ja' => ['betting' => '10908', 'default' => '10908'],
            'betking' => ['betting' => '10909', 'default' => '10909'],
            'nairabet' => ['betting' => '10910', 'default' => '10910'],
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

        // Protected wallet-to-wallet transfers: sender debited immediately, receiver
        // credited as pending (held_balance) until release or refund.
    ],

    /*
    |--------------------------------------------------------------------------
    | Digital marketplace
    |--------------------------------------------------------------------------
    |
    | confirm_hours: after the seller delivers a digital item, the buyer has
    | this long to release payment or raise a callback before auto-release.
    |
    */

    'digital' => [
        // Buyer confirmation window after the seller delivers.
        'confirm_hours' => (int) env('RETON_DIGITAL_CONFIRM_HOURS', 48),

        // Seller must deliver within this window or the buyer is auto-refunded.
        'delivery_deadline_hours' => (int) env('RETON_DIGITAL_DELIVERY_DEADLINE_HOURS', 72),

        // Grace period after purchase before a "not delivered" dispute is allowed.
        'dispute_grace_hours' => (int) env('RETON_DIGITAL_DISPUTE_GRACE_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Physical marketplace (Giglogistics)
    |--------------------------------------------------------------------------
    */

    'physical' => [
        'ship_deadline_hours' => (int) env('RETON_PHYSICAL_SHIP_DEADLINE_HOURS', 48),
        'confirm_hours' => (int) env('RETON_PHYSICAL_CONFIRM_HOURS', 72),
        'dispute_grace_hours' => (int) env('RETON_PHYSICAL_DISPUTE_GRACE_HOURS', 48),
        'verification_pass_score' => (int) env('RETON_PHYSICAL_VERIFICATION_PASS_SCORE', 70),
        'hub_verification_pass_score' => (int) env('RETON_PHYSICAL_HUB_PASS_SCORE', 80),
        'default_hub_name' => env('RETON_GIGLOGISTICS_HUB_NAME', 'Giglogistics Verification Hub — Lekki'),
        'default_hub_address' => [
            'line1' => env('RETON_GIGLOGISTICS_HUB_LINE1', '12 Admiralty Way'),
            'city' => env('RETON_GIGLOGISTICS_HUB_CITY', 'Lekki'),
            'state' => env('RETON_GIGLOGISTICS_HUB_STATE', 'Lagos'),
            'phone' => env('RETON_GIGLOGISTICS_HUB_PHONE', '+234 700 GIG LOG'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shareable links & mobile deep linking
    |--------------------------------------------------------------------------
    |
    | public_base: canonical HTTPS origin for listing links pasted in WhatsApp.
    | listing_path: stable path prefix claimed by future iOS/Android apps (/l/*).
    | app_scheme: custom-scheme fallback (reton://l/{uuid}) before store apps ship.
    |
    */

    'links' => [
        'public_base' => rtrim((string) env('RETON_PUBLIC_URL', env('APP_URL', 'http://localhost')), '/'),
        'listing_path' => env('RETON_LISTING_PATH', '/l'),
        'app_scheme' => env('RETON_APP_SCHEME', 'reton'),
        'mobile' => [
            'ios_bundle_id' => env('RETON_IOS_BUNDLE_ID', 'ng.reton.app'),
            'apple_team_id' => env('RETON_APPLE_TEAM_ID', ''),
            'android_package' => env('RETON_ANDROID_PACKAGE', 'ng.reton.app'),
            'android_sha256' => env('RETON_ANDROID_SHA256', ''),
        ],
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
    | KYC tiers (CBN-style limits + ALATPay static wallet mapping)
    |--------------------------------------------------------------------------
    |
    | Tier 1 — basic: collection static wallet (business BVN on ALATPay).
    | Tier 2 — BVN verified: individual static wallet.
    | Tier 3 — NIN + address: highest limits.
    | Amounts are in minor units (kobo).
    |
    */

    'kyc' => [
        'tiers' => [
            1 => [
                'single_transaction_max' => (int) env('RETON_KYC_T1_SINGLE_MAX', 50_000_00),
                'daily_inflow_max' => (int) env('RETON_KYC_T1_DAILY_IN_MAX', 200_000_00),
                'wallet_balance_max' => (int) env('RETON_KYC_T1_BALANCE_MAX', 300_000_00),
            ],
            2 => [
                'single_transaction_max' => (int) env('RETON_KYC_T2_SINGLE_MAX', 500_000_00),
                'daily_inflow_max' => (int) env('RETON_KYC_T2_DAILY_IN_MAX', 2_000_000_00),
                'wallet_balance_max' => (int) env('RETON_KYC_T2_BALANCE_MAX', 5_000_000_00),
            ],
            3 => [
                'single_transaction_max' => (int) env('RETON_KYC_T3_SINGLE_MAX', 5_000_000_00),
                'daily_inflow_max' => (int) env('RETON_KYC_T3_DAILY_IN_MAX', 20_000_000_00),
                'wallet_balance_max' => (int) env('RETON_KYC_T3_BALANCE_MAX', 50_000_000_00),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Virtual cards (Bridgecard Issuing)
    |--------------------------------------------------------------------------
    | @see https://docs.bridgecard.co/
    */

    'cards' => [
        'provider' => env('RETON_CARD_PROVIDER', 'bridgecard'),
        'currencies' => ['NGN', 'USD'],
        'min_funding_minor' => [
            'NGN' => (int) env('RETON_CARD_MIN_FUNDING_NGN', 1_000_00),
            'USD' => (int) env('RETON_CARD_MIN_FUNDING_USD', 300),
        ],
        'default_usd_limit' => env('RETON_CARD_USD_LIMIT', '500000'),
    ],

    'fx' => [
        // Retail rate: 1 USD = X NGN (major units, e.g. 1600 = ₦1,600/$)
        'usd_ngn_rate' => (float) env('RETON_FX_USD_NGN', 1600),
        'spread_bps' => (int) env('RETON_FX_SPREAD_BPS', 150),
    ],

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
