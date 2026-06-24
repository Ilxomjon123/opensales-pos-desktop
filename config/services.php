<?php

return [

    // OpenSales app-level bot (Telegram orqali kirish uchun, dillerlar botidan alohida).
    'opensales_bot' => [
        'token' => env('OPENSALES_BOT_TOKEN'),
        'username' => env('OPENSALES_BOT_USERNAME'),
    ],

    // SMS (OTP) — driver: log (dev) | eskiz (prod)
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],
    'eskiz' => [
        'base_url' => env('ESKIZ_BASE_URL', 'https://notify.eskiz.uz'),
        'email' => env('ESKIZ_EMAIL'),
        'password' => env('ESKIZ_PASSWORD'),
        'from' => env('ESKIZ_FROM', '4546'),
    ],

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

    'yandex' => [
        'routing_api_key' => env('YANDEX_ROUTING_API_KEY'),
        'routing_endpoint' => env(
            'YANDEX_ROUTING_ENDPOINT',
            'https://api.routing.yandex.net/v2/distancematrix',
        ),
        'routing_mode' => env('YANDEX_ROUTING_MODE', 'driving'),
    ],

    'openrouteservice' => [
        'api_key' => env('OPENROUTESERVICE_API_KEY'),
        'endpoint' => env(
            'OPENROUTESERVICE_ENDPOINT',
            'https://api.openrouteservice.org/v2/matrix',
        ),
        'profile' => env('OPENROUTESERVICE_PROFILE', 'driving-car'),
    ],

    'routing' => [
        // 'yandex' yoki 'openrouteservice'
        'provider' => env('ROUTING_PROVIDER', 'yandex'),
    ],

    'indexnow' => [
        'key' => env('INDEXNOW_KEY'),
        'endpoint' => env('INDEXNOW_ENDPOINT', 'https://api.indexnow.org/IndexNow'),
        'host' => env('INDEXNOW_HOST', 'opensales.uz'),
    ],

    // Mobil ilova OTP yetkazib berish. 'log' — kodni log ga yozadi (dev).
    // Keyinchalik 'eskiz'/'playmobile' drayveri qo'shiladi.
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        // Non-prod da OTP barqaror '000000' bo'ladi (qo'lda test uchun).
        'fake_code' => env('SMS_FAKE_CODE', true),
        // App Store / Google Play reviewer uchun maxsus test raqami: prod'da ham
        // SMS yubormay, REVIEW_OTP_CODE bilan kirishga ruxsat beradi. Ikkalasi
        // ham .env da to'ldirilganda faollashadi; bo'sh bo'lsa o'chiq.
        'review_phone' => env('REVIEW_OTP_PHONE'),
        'review_code' => env('REVIEW_OTP_CODE'),
    ],

];
