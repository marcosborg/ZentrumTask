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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
    ],

    'fcm' => [
        'service_account' => env('FCM_SERVICE_ACCOUNT', 'private/firebase-service-account.json'),
        'project_id' => env('FCM_PROJECT_ID'),
        'android_channel_id' => env('FCM_ANDROID_CHANNEL_ID', 'new-contacts-alerts'),
    ],

    'ifthenpay' => [
        'base_url' => env('IFTHENPAY_BASE_URL', 'https://api.ifthenpay.com'),
        'sandbox' => env('IFTHENPAY_SANDBOX', false),
        'mb_key' => env('IFTHENPAY_MB_KEY'),
        'backoffice_key' => env('IFTHENPAY_BACKOFFICE_KEY'),
        'entity' => env('IFTHENPAY_ENTITY', '12133'),
        'sub_entity' => env('IFTHENPAY_SUB_ENTITY', '054'),
        'anti_phishing_key' => env('IFTHENPAY_ANTI_PHISHING_KEY'),
        'expiry_days' => env('IFTHENPAY_EXPIRY_DAYS'),
        'initial_deposit_amount' => env('IFTHENPAY_INITIAL_DEPOSIT_AMOUNT', 250),
        'initial_deposit_vat_rate' => env('IFTHENPAY_INITIAL_DEPOSIT_VAT_RATE', 23),
    ],

];
