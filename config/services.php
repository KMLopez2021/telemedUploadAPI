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

    'appointment_api' => [
        'url' => env('APPOINTMENT_API_URL'),
        'token' => env('APPOINTMENT_API_TOKEN'),
        'candidates_path' => env('APPOINTMENT_API_CANDIDATES_PATH', '/api/internal/appointment-email-candidates'),
        'status_path' => env('APPOINTMENT_API_STATUS_PATH', '/api/internal/appointments/{activity_id}/email-status'),
        'timeout' => (int) env('APPOINTMENT_API_TIMEOUT', 15),
        'claim_minutes' => (int) env('APPOINTMENT_API_CLAIM_MINUTES', 30),
        'sending_lease_minutes' => (int) env('APPOINTMENT_EMAIL_SENDING_LEASE_MINUTES', 10),
        'reminder_timezone' => env('APPOINTMENT_REMINDER_TIMEZONE', 'Asia/Manila'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
