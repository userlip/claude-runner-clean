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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', '/admin/github/callback'),
    ],

    'codex' => [
        'path' => env('CODEX_PATH', '/home/ploi/.npm-global/bin/codex'),
    ],

    'ploi' => [
        'server_id' => env('PLOI_SERVER_ID'),
        'server_name' => env('PLOI_SERVER_NAME'),
        'api_url' => env('PLOI_API_URL', 'https://ploi.io/api'),
        'api_token' => env('PLOI_API_TOKEN'),
    ],

    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT'),
    ],

    'security_ai' => [
        'orchestrator_provider_id' => env('SECURITY_ORCHESTRATOR_PROVIDER_ID'),
        'fixer_provider_id' => env('SECURITY_FIXER_PROVIDER_ID'),
        'no_checks_grace_minutes' => env('SECURITY_NO_CHECKS_GRACE_MINUTES', 60),
        'fixing_ci_grace_minutes' => env('SECURITY_FIXING_CI_GRACE_MINUTES', 30),
    ],

];
