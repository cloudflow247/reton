<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'alatpay' => [
        // 'http' for the live integration, 'fake' for local/testing.
        // Official API host: https://apibox.alatpay.ng (see https://docs.alatpay.ng/get-started).
        // Live Static Wallet requires merchant login session + subscriptionPrimaryKey from login.
        'driver' => env('ALATPAY_DRIVER', 'http'),
        'base_url' => env('ALATPAY_BASE_URL', 'https://apibox.alatpay.ng'),
        'api_key' => env('ALATPAY_API_KEY'),
        'merchant_email' => env('ALATPAY_MERCHANT_EMAIL'),
        'merchant_password' => env('ALATPAY_MERCHANT_PASSWORD'),
        'business_id' => env('ALATPAY_BUSINESS_ID'),
        'business_bvn' => env('ALATPAY_BUSINESS_BVN'),
        'webhook_secret' => env('ALATPAY_WEBHOOK_SECRET', ''),
        'timeout' => (int) env('ALATPAY_TIMEOUT', 12),
    ],

    'remita' => [
        // The bill-payment provider: 'http' for the live integration, 'fake'
        // for local/testing.
        'driver' => env('REMITA_DRIVER', 'http'),
        'base_url' => env('REMITA_BASE_URL', 'https://api.remita.net'),
        'merchant_id' => env('REMITA_MERCHANT_ID'),
        'api_key' => env('REMITA_API_KEY'),
        'api_secret' => env('REMITA_API_SECRET'),
        'timeout' => (int) env('REMITA_TIMEOUT', 15),
    ],

    'giglogistics' => [
        'driver' => env('GIGLOGISTICS_DRIVER', 'fake'),
        'base_url' => env('GIGLOGISTICS_BASE_URL', 'https://api.giglogistics.com'),
        'api_key' => env('GIGLOGISTICS_API_KEY'),
        'webhook_secret' => env('GIGLOGISTICS_WEBHOOK_SECRET', ''),
        'fake_advance_minutes' => (int) env('GIGLOGISTICS_FAKE_ADVANCE_MINUTES', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Interswitch Quickteller (bill payments & airtime)
    |--------------------------------------------------------------------------
    | @see https://docs.interswitchgroup.com/docs/bills-payment-1
    */
    'interswitch' => [
        'driver' => env('INTERSWITCH_DRIVER', 'http'),
        'passport_url' => env('INTERSWITCH_PASSPORT_URL', 'https://qa.interswitchng.com/passport/oauth/token'),
        'base_url' => env('INTERSWITCH_BASE_URL', 'https://qa.interswitchng.com/quicktellerservice/api/v5'),
        'terminal_id' => env('INTERSWITCH_TERMINAL_ID'),
        'client_id' => env('INTERSWITCH_CLIENT_ID'),
        'client_secret' => env('INTERSWITCH_CLIENT_SECRET'),
        'request_reference_prefix' => env('INTERSWITCH_REF_PREFIX', '1453'),
        'timeout' => (int) env('INTERSWITCH_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bridgecard Issuing (virtual NGN & USD cards)
    |--------------------------------------------------------------------------
    | @see https://docs.bridgecard.co/
    */
    'bridgecard' => [
        'driver' => env('BRIDGECARD_DRIVER', 'fake'),
        'base_url' => env('BRIDGECARD_BASE_URL', 'https://issuecards.api.bridgecard.co/v1/issuing/sandbox'),
        'access_token' => env('BRIDGECARD_ACCESS_TOKEN'),
        'secret_key' => env('BRIDGECARD_SECRET_KEY'),
        'timeout' => (int) env('BRIDGECARD_TIMEOUT', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dojah (BVN / NIN identity verification)
    |--------------------------------------------------------------------------
    | @see https://docs.dojah.io
    */
    'kyc' => [
        // BVN verification for wallet funding: alatpay (OTP via static wallet) or dojah.
        'bvn_provider' => env('KYC_BVN_PROVIDER', 'alatpay'),
    ],

    'dojah' => [
        'driver' => env('DOJAH_DRIVER', 'fake'),
        'base_url' => env('DOJAH_BASE_URL', 'https://sandbox.dojah.io'),
        'app_id' => env('DOJAH_APP_ID'),
        'secret_key' => env('DOJAH_SECRET_KEY'),
        'timeout' => (int) env('DOJAH_TIMEOUT', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Termii (SMS & WhatsApp OTP)
    |--------------------------------------------------------------------------
    | @see https://developers.termii.com/
    */
    'termii' => [
        'driver' => env('TERMII_DRIVER', 'fake'),
        'base_url' => env('TERMII_BASE_URL', 'https://api.ng.termii.com'),
        'api_key' => env('TERMII_API_KEY'),
        'sender_id' => env('TERMII_SENDER_ID', 'Reton'),
        'channel' => env('TERMII_CHANNEL', 'generic'),
        'timeout' => (int) env('TERMII_TIMEOUT', 15),
    ],

];
