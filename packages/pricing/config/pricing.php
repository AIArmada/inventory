<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'tables' => [
            'prices' => 'prices',
            'price_lists' => 'price_lists',
            'price_tiers' => 'price_tiers',
            'promotions' => 'promotions',
            'promotionables' => 'promotionables',
        ],

        'json_column_type' => env('PRICING_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'currency' => 'MYR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features/Behavior
    |--------------------------------------------------------------------------
    */
    'features' => [
        'owner' => [
            'enabled' => env('PRICING_OWNER_ENABLED', false),
            'include_global' => false,
        ],
    ],
];
