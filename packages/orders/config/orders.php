<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Configure the database table names used by the orders package.
    |
    */
    'database' => [
        'tables' => [
            'orders' => 'orders',
            'order_items' => 'order_items',
            'order_addresses' => 'order_addresses',
            'order_payments' => 'order_payments',
            'order_refunds' => 'order_refunds',
            'order_notes' => 'order_notes',
        ],
        'json_column_type' => env('ORDERS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Default currency for orders.
    |
    */
    'currency' => [
        'default' => 'MYR',
        'decimal_places' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'owner' => [
        'enabled' => true,
        'include_global' => true,
        'auto_assign_on_create' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Number Generation
    |--------------------------------------------------------------------------
    |
    | Configure how order numbers are generated.
    |
    */
    'order_number' => [
        'prefix' => env('ORDERS_ORDER_NUMBER_PREFIX', 'ORD'),
        'separator' => env('ORDERS_ORDER_NUMBER_SEPARATOR', '-'),
        'length' => env('ORDERS_ORDER_NUMBER_LENGTH', 8),
        'use_date' => env('ORDERS_ORDER_NUMBER_USE_DATE', true),
        'date_format' => env('ORDERS_ORDER_NUMBER_DATE_FORMAT', 'Ymd'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice
    |--------------------------------------------------------------------------
    |
    | Configure invoice number generation.
    |
    */
    'invoice' => [
        'prefix' => env('ORDERS_INVOICE_PREFIX', 'INV'),
        'separator' => env('ORDERS_INVOICE_SEPARATOR', '-'),
        'random_length' => env('ORDERS_INVOICE_RANDOM_LENGTH', 6),
        'date_format' => env('ORDERS_INVOICE_DATE_FORMAT', 'Ymd'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    |
    | Configure optional package integrations.
    |
    */
    'integrations' => [
        'inventory' => [
            'enabled' => true,
            'deduct_on' => 'payment_confirmed', // When to deduct inventory
        ],
        'affiliates' => [
            'enabled' => true,
            'attribute_on' => 'payment_confirmed', // When to attribute commissions
        ],
        'shipping' => [
            'enabled' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auditing
    |--------------------------------------------------------------------------
    |
    | Configure compliance-grade auditing with owen-it/laravel-auditing.
    |
    */
    'audit' => [
        'enabled' => env('ORDERS_AUDIT_ENABLED', true),
        'threshold' => env('ORDERS_AUDIT_THRESHOLD', 500),
    ],
];
