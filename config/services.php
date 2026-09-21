<?php

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

    'ghl' => [
        'base_url' => env('GHL_BASE_URL', 'https://services.leadconnectorhq.com'),
        'access_token' => env('GHL_ACCESS_TOKEN'),
        'email_stats_access_token' => env('GHL_EMAIL_STATS_ACCESS_TOKEN', env('GHL_ACCESS_TOKEN')),
        'version' => env('GHL_API_VERSION', '2021-07-28'),
        'email_stats_version' => env('GHL_EMAIL_STATS_API_VERSION', 'v3'),
        'email_stats_timeout' => (int) env('GHL_EMAIL_STATS_TIMEOUT', 3),
        'email_stats_budget' => (int) env('GHL_EMAIL_STATS_BUDGET', 20),
        'email_stats_max_pages' => (int) env('GHL_EMAIL_STATS_MAX_PAGES', 5),
        'email_stats_max_items' => (int) env('GHL_EMAIL_STATS_MAX_ITEMS', 40),
        'company_id' => env('GHL_COMPANY_ID'),
        'location_id' => env('GHL_LOCATION_ID'),
        'audit_report_url_field_id' => env('GHL_AUDIT_REPORT_URL_FIELD_ID', '9T4jIzXW21RYkBzmWoj1'),
        'timeout' => (int) env('GHL_TIMEOUT', 10),
        // Pages fetched in parallel during a full sync. GHL allows 100 requests per 10 seconds.
        'sync_concurrency' => (int) env('GHL_SYNC_CONCURRENCY', 10),
        'webhook_secret' => env('GHL_WEBHOOK_SECRET'),
    ],

    'google_calendar' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID', env('Client_ID')),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET', env('Client_Secret')),
        'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost:8000'), '/').'/integrations/google-calendar/callback'),
        'timezone' => env('GOOGLE_CALENDAR_TIMEZONE', 'Asia/Bangkok'),
    ],

];
