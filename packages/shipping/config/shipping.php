<?php

declare(strict_types=1);

$tablePrefix = env('SHIPPING_TABLE_PREFIX', env('COMMERCE_TABLE_PREFIX', ''));

$tables = [
    'shipments' => $tablePrefix . 'shipments',
    'shipment_items' => $tablePrefix . 'shipment_items',
    'shipment_labels' => $tablePrefix . 'shipment_labels',
    'shipment_events' => $tablePrefix . 'shipment_events',
    'shipping_zones' => $tablePrefix . 'shipping_zones',
    'shipping_rates' => $tablePrefix . 'shipping_rates',
    'return_authorizations' => $tablePrefix . 'return_authorizations',
    'return_authorization_items' => $tablePrefix . 'return_authorization_items',
];

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('SHIPPING_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => $tables,
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'currency' => 'MYR',
        'reference_prefix' => env('SHIPPING_REFERENCE_PREFIX', 'SHP-'),
        'origin' => [
            'name' => env('SHIPPING_ORIGIN_NAME', env('APP_NAME', 'Store')),
            'phone' => env('SHIPPING_ORIGIN_PHONE', ''),
            'address' => env('SHIPPING_ORIGIN_ADDRESS', ''),
            'post_code' => env('SHIPPING_ORIGIN_POST_CODE', ''),
            'country_code' => env('SHIPPING_ORIGIN_COUNTRY_CODE', 'MYS'),
            'state' => env('SHIPPING_ORIGIN_STATE'),
            'city' => env('SHIPPING_ORIGIN_CITY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Shipping Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default shipping driver that will be used when
    | no specific driver is requested. The driver must be registered in the
    | drivers array below or extended via ShippingManager::extend().
    |
    */
    'default' => env('SHIPPING_DRIVER', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Shipping Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the shipping drivers for your application.
    | Carrier packages auto-register via extend(). This configuration is
    | for built-in drivers or manual overrides.
    |
    */
    'drivers' => [
        'manual' => [
            'driver' => 'manual',
            'name' => 'Manual Shipping',
            'default_rate' => 1000, // RM10.00 in cents
            'estimated_days' => 3,
            'free_shipping_threshold' => null,
        ],

        'flat_rate' => [
            'driver' => 'flat_rate',
            'name' => 'Flat Rate Shipping',
            'rates' => [
                'standard' => [
                    'name' => 'Standard Delivery',
                    'rate' => 800, // RM8.00
                    'estimated_days' => 3,
                ],
                'express' => [
                    'name' => 'Express Delivery',
                    'rate' => 1500, // RM15.00
                    'estimated_days' => 1,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Shopping Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how rate shopping works when comparing rates from multiple
    | carriers. The strategy determines which rate is selected by default.
    |
    */
    'rate_shopping' => [
        'strategy' => 'cheapest', // cheapest, fastest, preferred
        'cache_ttl' => 300, // seconds
        'fallback_to_manual' => true,
        'carrier_priority' => [
            // 'jnt' => 1,
            // 'poslaju' => 2,
            // 'gdex' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Free Shipping Configuration
    |--------------------------------------------------------------------------
    |
    | Configure global free shipping rules. These can be overridden by
    | zone-specific or carrier-specific settings.
    |
    */
    'free_shipping' => [
        'enabled' => false,
        'threshold' => 15000, // RM150.00 in cents
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic tracking synchronization and webhook handling.
    |
    */
    'tracking' => [
        'sync_interval' => 3600, // 1 hour in seconds
        'max_tracking_age' => 30, // days to keep syncing
    ],
];
