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

    /*
    | البحث عن الكتب بالـ ISBN: ترتيب المزوّدين، ويُتجاوز أي مزوّد بلا بيانات اعتماد.
    */
    'isbn_lookup' => [
        'providers' => array_values(array_filter(array_map('trim', explode(',', (string) env('ISBN_LOOKUP_PROVIDERS', 'openlibrary,googlebooks,worldcat,amazon'))))),
        'cache_minutes' => (int) env('ISBN_LOOKUP_CACHE_MINUTES', 1440),
        'timeout' => (int) env('ISBN_LOOKUP_TIMEOUT', 8),
    ],

    'google_books' => [
        'key' => env('GOOGLE_BOOKS_API_KEY'),
    ],

    'worldcat' => [
        'client_id' => env('WORLDCAT_CLIENT_ID'),
        'client_secret' => env('WORLDCAT_CLIENT_SECRET'),
    ],

    'amazon' => [
        'access_key' => env('AMAZON_PAAPI_ACCESS_KEY'),
        'secret_key' => env('AMAZON_PAAPI_SECRET_KEY'),
        'partner_tag' => env('AMAZON_PAAPI_PARTNER_TAG'),
        'host' => env('AMAZON_PAAPI_HOST', 'webservices.amazon.com'),
        'region' => env('AMAZON_PAAPI_REGION', 'us-east-1'),
        'marketplace' => env('AMAZON_PAAPI_MARKETPLACE', 'www.amazon.com'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
