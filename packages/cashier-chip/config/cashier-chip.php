<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => env('CASHIER_CHIP_TABLE_PREFIX', 'cashier_chip_'),
        'json_column_type' => env('CASHIER_CHIP_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => (static function (): array {
            $prefix = env('CASHIER_CHIP_TABLE_PREFIX', 'cashier_chip_');

            return [
                'subscriptions' => $prefix . 'subscriptions',
                'subscription_items' => $prefix . 'subscription_items',
            ];
        })(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'currency' => env('CASHIER_CHIP_CURRENCY', 'MYR'),
    'currency_locale' => env('CASHIER_CHIP_CURRENCY_LOCALE', 'ms_MY'),

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'owner' => [
            'enabled' => env('CASHIER_CHIP_OWNER_ENABLED', true),
            'include_global' => env('CASHIER_CHIP_OWNER_INCLUDE_GLOBAL', false),
            'auto_assign_on_create' => env('CASHIER_CHIP_OWNER_AUTO_ASSIGN_ON_CREATE', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Behavior
    |--------------------------------------------------------------------------
    */
    'subscriptions' => [
        'retry_days' => env('CASHIER_CHIP_RETRY_DAYS', 3),
        'max_retries' => env('CASHIER_CHIP_MAX_RETRIES', 3),
        'grace_days' => env('CASHIER_CHIP_GRACE_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
     */
    'path' => env('CASHIER_CHIP_PATH', 'chip'),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
     */
    'webhooks' => [
        'secret' => env('CHIP_WEBHOOK_SECRET'),
        'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoices
    |--------------------------------------------------------------------------
    */
    'invoices' => [
        'renderer' => env('CASHIER_CHIP_INVOICE_RENDERER'),
        'paper' => env('CASHIER_CHIP_PAPER', 'A4'),
        'vendor_address' => env('CASHIER_CHIP_INVOICE_VENDOR_ADDRESS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logger' => env('CASHIER_CHIP_LOGGER'),

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
];
