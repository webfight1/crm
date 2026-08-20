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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'pagespeed' => [
        'key' => env('PAGESPEED_API_KEY'),
    ],

    'design_age' => [
        // Minimum CSS content similarity (%) for two Wayback snapshots to count
        // as "the same design" when estimating website design age.
        'threshold' => env('DESIGN_AGE_SIMILARITY_THRESHOLD', 85),

        // Minimum gap (ms) between consecutive archive.org requests. The Wayback
        // Machine throttles hard, so pace requests to avoid 429s / IP blocks.
        'request_delay_ms' => env('DESIGN_AGE_REQUEST_DELAY_MS', 1500),
    ],

];
