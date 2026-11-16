<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pesapal Environment
    |--------------------------------------------------------------------------
    |
    | Set to 'sandbox' for testing or 'live' for production.
    | This determines the base API URL and which credentials are used.
    |
    */
    'environment' => env('PESAPAL_ENV', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Pesapal Credentials
    |--------------------------------------------------------------------------
    |
    | Consumer Key and Secret for authenticating with Pesapal API.
    | These should be set in your .env file.
    |
    */
    'consumer_key' => env('PESAPAL_CONSUMER_KEY'),
    'consumer_secret' => env('PESAPAL_CONSUMER_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | API URLs
    |--------------------------------------------------------------------------
    |
    | Base URLs for Sandbox and Production environments.
    |
    */
    'base_urls' => [
        'sandbox' => 'https://cybqa.pesapal.com/pesapalv3',
        'live' => 'https://pay.pesapal.com/v3',
    ],

    /*
    |--------------------------------------------------------------------------
    | IPN (Instant Payment Notification) Configuration
    |--------------------------------------------------------------------------
    |
    | The notification_id returned when registering the IPN URL.
    | This is automatically saved after running `php artisan pesapal:register-ipn`
    |
    */
    'ipn' => [
        'notification_id' => env('PESAPAL_IPN_ID'),
        'ipn_type' => env('PESAPAL_IPN_TYPE', 'POST'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback URLs
    |--------------------------------------------------------------------------
    |
    | URLs where Pesapal will redirect after payment.
    | These are used when submitting the order to Pesapal.
    |
    */
    'callbacks' => [
        'return_url' => env('PESAPAL_RETURN_URL'),
        'cancel_url' => env('PESAPAL_CANCEL_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Cache
    |--------------------------------------------------------------------------
    |
    | Configuration for access token caching.
    | Token expires in 5 minutes, so we cache for 4 minutes (240 seconds).
    |
    */
    'token_cache_ttl' => env('PESAPAL_TOKEN_CACHE_TTL', 240),
];
