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
        'version' => env('GHL_API_VERSION', '2021-07-28'),
        'company_id' => env('GHL_COMPANY_ID'),
        'location_id' => env('GHL_LOCATION_ID'),
        'audit_report_url_field_id' => env('GHL_AUDIT_REPORT_URL_FIELD_ID', '9T4jIzXW21RYkBzmWoj1'),
        'timeout' => (int) env('GHL_TIMEOUT', 10),
    ],

];
