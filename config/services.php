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

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS', env('GOOGLE_APPLICATION_CREDENTIALS')),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'snap_base_url' => env(
            'MIDTRANS_SNAP_BASE_URL',
            (bool) env('MIDTRANS_IS_PRODUCTION', false)
                ? 'https://app.midtrans.com/snap/v1'
                : 'https://app.sandbox.midtrans.com/snap/v1'
        ),
        'notification_url' => env('MIDTRANS_NOTIFICATION_URL'),
        'enabled_payments' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('MIDTRANS_ENABLED_PAYMENTS', 'bni_va'))
        ))),
        'expiry_duration_hours' => (int) env('MIDTRANS_EXPIRY_DURATION_HOURS', 24),
        'timeout' => (int) env('MIDTRANS_TIMEOUT', 15),
    ],

];
