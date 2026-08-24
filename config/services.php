<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),

        // Verifies the open-tracking webhook. Left empty the endpoint rejects
        // everything, which is the right default: an unconfigured webhook
        // should be closed, not open (FR-064).
        'webhook_secret' => env('RESEND_WEBHOOK_SECRET', ''),
    ],

    /*
     * VAPID — the keypair that identifies this server to a browser's push
     * service. Generate one with `php artisan byagain:vapid-keys` and paste
     * the output into `.env` by hand; the command writes no files and nothing
     * in the codebase writes `.env` (art. III).
     *
     * Absent, the feature is simply off: the subscription endpoint answers
     * 503 and the settings toggle renders disabled. That is the right default
     * for a key nobody has set yet.
     */
    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),

        // A `mailto:` or `https:` URL identifying whoever runs this server,
        // so a push service has somebody to contact about it. Required by the
        // VAPID spec.
        'subject' => env('VAPID_SUBJECT'),
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

];
