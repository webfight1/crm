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

    'telegram' => [
        'token'   => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    // SEO-monitor (seo.webfight.ee) — the SEO pipeline creates a project there
    // for every won SEO client. Token = Sanctum token of an admin service user.
    'seo_monitor' => [
        'api_url' => env('SEO_MONITOR_API_URL'),
        'token'   => env('SEO_MONITOR_API_TOKEN'),
        'app_url' => env('SEO_MONITOR_APP_URL', 'https://seo.webfight.ee'),
    ],

    'clickup' => [
        // Personal API token from ClickUp → Settings → Apps ("pk_...").
        'token' => env('CLICKUP_API_TOKEN'),

        // Workspace id — the first number in an app.clickup.com URL.
        'team_id' => env('CLICKUP_TEAM_ID'),

        // One-click sources shown on /outreach/clickup. Any list or view URL
        // can still be pasted by hand; these are just the ones we use often.
        'lists' => [
            [
                'label' => 'Külmad kontaktid — MÜÜK (MAR)',
                'url'   => 'https://app.clickup.com/9015331367/v/li/901519221606',
            ],
            [
                'label' => 'Külmad kontaktid — MÜÜK (Kristina)',
                'url'   => 'https://app.clickup.com/9015331367/v/l/li/901523799837',
            ],
        ],
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
