<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fleetbase integration
    |--------------------------------------------------------------------------
    |
    | When an order is marked "ready for pickup" the details are pushed to a
    | Fleetbase instance as a shipping order. Set FLEETBASE_ENABLED=false to
    | disable the integration without removing the admin button.
    |
    */

    'enabled' => env('FLEETBASE_ENABLED', true),

    'base_url' => env('FLEETBASE_BASE_URL', 'http://127.0.0.1:9099'),

    'api_key' => env('FLEETBASE_API_KEY', ''),

    'timeout' => (int) env('FLEETBASE_TIMEOUT', 15),

    /*
    | Pickup (warehouse) location used as the Fleetbase order pickup place.
    */
    'pickup' => [
        'name' => env('FLEETBASE_PICKUP_NAME', 'Main Warehouse'),
        'street1' => env('FLEETBASE_PICKUP_STREET1', '1 Warehouse Way'),
        'city' => env('FLEETBASE_PICKUP_CITY', 'Metropolis'),
        'province' => env('FLEETBASE_PICKUP_PROVINCE', 'NY'),
        'postal_code' => env('FLEETBASE_PICKUP_POSTAL', '10001'),
        'country' => env('FLEETBASE_PICKUP_COUNTRY', 'US'),
        'phone' => env('FLEETBASE_PICKUP_PHONE', '555-0100'),
    ],

];
