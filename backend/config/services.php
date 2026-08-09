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

    // Backend -> ML Engine, per docs/backend/analysis/04-ml-client-contract.md
    // §3. No authentication scheme is mandated by any frozen document, so this
    // phase makes an explicit, flagged decision (SECURITY-004): a shared
    // bearer token, required, not optional. MLClient fails loudly at first
    // use if `token` is unset, rather than silently omitting the header.
    'ml_engine' => [
        'url' => env('ML_SERVICE_URL', 'http://localhost:8001'),
        'token' => env('ML_SERVICE_TOKEN'),
    ],

];
