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

    'platform_reports' => [
        'bolt' => [
            'directory' => env('BOLT_REPORTS_DIRECTORY', 'storage/app/platform-reports/bolt'),
        ],
        'uber' => [
            'directory' => env('UBER_REPORTS_DIRECTORY', 'storage/app/platform-reports/uber'),
        ],
    ],

    'uber_collector' => [
        'login_url' => env('UBER_COLLECTOR_LOGIN_URL', 'https://supplier.uber.com/sign-in'),
        'reports_url' => env('UBER_COLLECTOR_REPORTS_URL', 'https://supplier.uber.com/reports'),
        'email' => env('UBER_COLLECTOR_EMAIL'),
        'password' => env('UBER_COLLECTOR_PASSWORD'),
        'otp' => env('UBER_COLLECTOR_OTP'),
        'storage_state' => env('UBER_COLLECTOR_STORAGE_STATE', 'storage/app/private/uber-playwright-state.json'),
        'user_data_dir' => env('UBER_COLLECTOR_USER_DATA_DIR', 'storage/app/private/uber-playwright-profile'),
    ],

];
