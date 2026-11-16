<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public demo login
    |--------------------------------------------------------------------------
    |
    | When enabled, the login forms accept DEMO_LOGIN as a username and map it
    | to the shared demo account email. Every seeded account also gets
    | DEMO_PASSWORD. Do not enable this on a real deployment.
    |
    */

    'enabled' => env('DEMO_MODE', false),
    'login' => env('DEMO_LOGIN', 'admin'),
    'email' => env('DEMO_EMAIL', 'admin@example.com'),
    'password' => env('DEMO_PASSWORD', 'admin'),
];
