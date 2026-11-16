<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Juvo Logistics Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for integrating with the Juvo Logistics platform
    | (formerly witylogix) for order fulfillment, driver dispatch,
    | route optimization, and real-time tracking.
    |
    */

    'enabled' => env('JUVO_LOGISTICS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Portal Registration
    |--------------------------------------------------------------------------
    |
    | Public self-registration for logistics admins. Keep disabled in
    | production; create additional admins from the console instead.
    |
    */

    'registration_open' => env('JUVO_LOGISTICS_REGISTRATION_OPEN', false),

    /*
    |--------------------------------------------------------------------------
    | API Connection
    |--------------------------------------------------------------------------
    */
    'api' => [
        'base_url' => env('JUVO_LOGISTICS_API_URL', 'http://localhost/api/v4'),
        'api_key' => env('JUVO_LOGISTICS_API_KEY'),
        'api_secret' => env('JUVO_LOGISTICS_API_SECRET'),
        'timeout' => env('JUVO_LOGISTICS_TIMEOUT', 30),
        'retry_attempts' => env('JUVO_LOGISTICS_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('JUVO_LOGISTICS_RETRY_DELAY', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'secret' => env('JUVO_LOGISTICS_WEBHOOK_SECRET'),
        'signature_header' => 'X-Juvo-Signature',
        'allowed_ips' => array_filter(explode(',', env('JUVO_LOGISTICS_WEBHOOK_IPS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fulfillment Mapping
    |--------------------------------------------------------------------------
    |
    | Map Laravel order statuses to Juvo Logistics fulfillment types
    |
    */
    'fulfillment' => [
        'default_carrier' => env('JUVO_LOGISTICS_DEFAULT_CARRIER', 'juvo-fleet'),
        'pickup_location_mapping' => [
            // Map Laravel pickup locations to Juvo Logistics zones
        ],
        'auto_dispatch' => env('JUVO_LOGISTICS_AUTO_DISPATCH', true),
        'require_confirmation' => env('JUVO_LOGISTICS_REQUIRE_CONFIRMATION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking & Notifications
    |--------------------------------------------------------------------------
    */
    'tracking' => [
        'public_tracking_url' => env('JUVO_LOGISTICS_TRACKING_URL', 'http://localhost/track'),
        'embed_tracking' => env('JUVO_LOGISTICS_EMBED_TRACKING', true),
        'notify_customer_on_dispatch' => true,
        'notify_customer_on_delivery' => true,
        'notify_vendor_on_pickup' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver & Fleet Settings
    |--------------------------------------------------------------------------
    */
    'fleet' => [
        'min_driver_rating' => 4.0,
        'max_delivery_distance_km' => 50,
        'cod_enabled' => true,
        'proof_of_delivery_required' => true,
        'photo_proof_required' => true,
        'signature_required' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Finance Controls
    |--------------------------------------------------------------------------
    |
    | Optional approval limit for non-super-admin payout approvals. Set to 0
    | to disable the amount cap and rely on role permissions.
    |
    */
    'finance' => [
        'payout_approval_limit' => env('JUVO_LOGISTICS_PAYOUT_APPROVAL_LIMIT', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'order_status_sync_interval' => 5, // minutes
        'driver_location_sync_interval' => 30, // seconds
        'batch_size' => 50,
    ],
];
