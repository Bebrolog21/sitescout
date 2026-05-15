<?php

return [
    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [
        // Resend HTTPS API (resend/resend-laravel). Reads RESEND_API_KEY env.
        'resend' => [
            'transport' => 'resend',
        ],

        // SMTP transport (Mailtrap, generic SMTP). Kept for local/dev use.
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', 'sandbox.smtp.mailtrap.io'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        // Fallback chain: Resend API → log. If Resend rejects/times out,
        // the email is written to the application log instead of being lost.
        'failover' => [
            'transport' => 'failover',
            'mailers' => ['resend', 'log'],
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'onboarding@resend.dev'),
        'name' => env('MAIL_FROM_NAME', 'SiteScout'),
    ],
];
