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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM_NUMBER'),
    ],
    'bakong' => [

        'api_url' => env(
            'BAKONG_API_URL',
            'https://sit-api-bakong.nbc.org.kh'
        ),

        'api_token' => env('BAKONG_API_TOKEN'),

        'account_id' => env('BAKONG_ACCOUNT_ID'),

        'merchant_id' => env('BAKONG_MERCHANT_ID'),

        'acquiring_bank' => env('BAKONG_ACQUIRING_BANK'),

        'merchant_name' => env(
            'BAKONG_MERCHANT_NAME',
            'My Food App'
        ),

        'merchant_city' => env(
            'BAKONG_MERCHANT_CITY',
            'PHNOM PENH'
        ),

    ],
    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],
];
