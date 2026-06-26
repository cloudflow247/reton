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
        'driver' => env('ALATPAY_DRIVER', 'http'),
        'base_url' => env('ALATPAY_BASE_URL', 'https://api.alatpay.ng'),
        'api_key' => env('ALATPAY_API_KEY'),
        'business_id' => env('ALATPAY_BUSINESS_ID'),
        'business_bvn' => env('ALATPAY_BUSINESS_BVN'),
        'webhook_secret' => env('ALATPAY_WEBHOOK_SECRET', ''),
        'timeout' => (int) env('ALATPAY_TIMEOUT', 15),
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

];
