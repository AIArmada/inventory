<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Shipping Driver
    |--------------------------------------------------------------------------
    */
    'default' => env('SHIPPING_DRIVER', 'jnt'),

    /*
    |--------------------------------------------------------------------------
    | Origin Address
    |--------------------------------------------------------------------------
    |
    | The default origin address used when shipping doesn't have inventory
    | location awareness or no suitable warehouse is found.
    |
    */
    'origin' => [
        'name' => env('SHIPPING_ORIGIN_NAME', env('APP_NAME', 'Store')),
        'phone' => env('SHIPPING_ORIGIN_PHONE', ''),
        'line1' => env('SHIPPING_ORIGIN_LINE1', ''),
        'line2' => env('SHIPPING_ORIGIN_LINE2'),
        'city' => env('SHIPPING_ORIGIN_CITY', ''),
        'state' => env('SHIPPING_ORIGIN_STATE', ''),
        'postcode' => env('SHIPPING_ORIGIN_POSTCODE', ''),
        'country_code' => env('SHIPPING_ORIGIN_COUNTRY_CODE', 'MY'),
        'email' => env('SHIPPING_ORIGIN_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Shopping
    |--------------------------------------------------------------------------
    */
    'rate_shopping' => [
        'strategy' => 'cheapest',
        'fallback_to_manual' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Free Shipping
    |--------------------------------------------------------------------------
    */
    'free_shipping' => [
        'enabled' => false,
        'threshold' => 15000, // RM150.00 in cents
    ],
];
