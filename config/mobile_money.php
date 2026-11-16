<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mobile Money Providers
    |--------------------------------------------------------------------------
    |
    | The Tanzania mobile-money networks offered to shoppers and vendors.
    | `code` is the value stored with the order/campaign, `label` is shown to
    | the user, `msisdn_prefixes` are used for client-side validation, and
    | `aggregator_ref` is the identifier the payment aggregator expects.
    |
    */

    'providers' => [
        'mpesa' => [
            'logo' => 'images/mobile-money/mpesa.svg',
            'label' => 'M-Pesa (Vodacom)',
            'msisdn_prefixes' => ['74', '75', '76'],
            'aggregator_ref' => env('MOBILE_MONEY_REF_MPESA', 'M-PESA'),
            'enabled' => true,
        ],
        'mixx' => [
            'logo' => 'images/mobile-money/mixx.svg',
            'label' => 'Mixx by Yas (Tigo)',
            'msisdn_prefixes' => ['65', '67', '71'],
            'aggregator_ref' => env('MOBILE_MONEY_REF_MIXX', 'TIGO-PESA'),
            'enabled' => true,
        ],
        'airtel' => [
            'logo' => 'images/mobile-money/airtel.svg',
            'label' => 'Airtel Money',
            'msisdn_prefixes' => ['68', '69', '78', '79'],
            'aggregator_ref' => env('MOBILE_MONEY_REF_AIRTEL', 'AIRTEL'),
            'enabled' => true,
        ],
        'halopesa' => [
            'logo' => 'images/mobile-money/halopesa.svg',
            'label' => 'HaloPesa',
            'msisdn_prefixes' => ['62', '63'],
            'aggregator_ref' => env('MOBILE_MONEY_REF_HALOPESA', 'HALOPESA'),
            'enabled' => true,
        ],
        'tpesa' => [
            'logo' => 'images/mobile-money/tpesa.svg',
            'label' => 'T-Pesa',
            'msisdn_prefixes' => ['73'],
            'aggregator_ref' => env('MOBILE_MONEY_REF_TPESA', 'T-PESA'),
            'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregator Integration
    |--------------------------------------------------------------------------
    |
    | Juvo does not talk to each mobile network directly. A payment aggregator
    | (Selcom, Azampay, Beem, Flutterwave, DPO, ...) pushes a USSD/STK prompt to
    | the shopper's phone. The shopper enters their PIN on the handset; Juvo
    | never sees or stores the PIN.
    |
    | Leave `base_url` and `api_key` empty until an aggregator account exists.
    | While they are empty MobileMoney::isConfigured() is false: mobile money is
    | not offered at checkout or on ad campaigns, and a request that slips
    | through anyway is rejected instead of creating an unpaid order.
    |
    */

    'aggregator' => [
        'driver' => env('MOBILE_MONEY_DRIVER', 'generic'),
        'base_url' => env('MOBILE_MONEY_BASE_URL'),
        'api_key' => env('MOBILE_MONEY_API_KEY'),
        'api_secret' => env('MOBILE_MONEY_API_SECRET'),
        'vendor_id' => env('MOBILE_MONEY_VENDOR_ID'),
        'callback_url' => env('MOBILE_MONEY_CALLBACK_URL'),
        'callback_secret' => env('MOBILE_MONEY_CALLBACK_SECRET'),
        'callback_signature_header' => env('MOBILE_MONEY_SIGNATURE_HEADER', 'X-Signature'),
    ],

];
