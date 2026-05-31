<?php

return [
    'driver' => env('MAIL_DRIVER', 'log'),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name'    => env('MAIL_FROM_NAME', 'Flint'),
    ],

    // SMTP (also used by SES SMTP endpoint)
    'host'       => env('MAIL_HOST', '127.0.0.1'),
    'port'       => env('MAIL_PORT', 587),
    'username'   => env('MAIL_USERNAME', ''),
    'password'   => env('MAIL_PASSWORD', ''),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),

    'mailgun' => [
        'key'    => env('MAILGUN_SECRET', ''),
        'domain' => env('MAILGUN_DOMAIN', ''),
        'region' => env('MAILGUN_REGION', 'us'),   // 'us' or 'eu'
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN', ''),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID', ''),
        'secret' => env('AWS_SECRET_ACCESS_KEY', ''),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sendgrid' => [
        'key' => env('SENDGRID_API_KEY', ''),
    ],
];
