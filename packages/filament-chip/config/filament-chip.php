<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'CHIP Operations',
        'badge_color' => 'primary',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'polling_interval' => '45s',

    'tables' => [
        'created_on_format' => 'Y-m-d H:i:s',
        'updated_on_format' => 'Y-m-d H:i:s',
        'amount_precision' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'default_currency' => 'MYR',

    /*
    |--------------------------------------------------------------------------
    | Billing Portal
    |--------------------------------------------------------------------------
    */
    'billing' => [
        'enabled' => env('CHIP_BILLING_PORTAL_ENABLED', true),
        'panel_id' => 'billing',
        'path' => 'billing',
        'brand_name' => env('CHIP_BILLING_BRAND_NAME', 'Billing Portal'),
        'primary_color' => env('CHIP_BILLING_PRIMARY_COLOR', '#6366f1'),
        'login_enabled' => env('CHIP_BILLING_LOGIN_ENABLED', true),
        'auth_guard' => 'web',
        'allowed_roles' => [],
        'billable_model' => null,
        'features' => [
            'subscriptions' => true,
            'payment_methods' => true,
            'invoices' => true,
        ],
        'redirects' => [
            'after_payment_method_added' => null,
            'after_subscription_cancelled' => null,
        ],
        'invoice' => [
            'vendor_name' => null,
            'product_name' => 'Subscription',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */
    'resources' => [
        'navigation_sort' => [
            'purchases' => 10,
            'payments' => 20,
            'clients' => 30,
            'bank_accounts' => 40,
            'webhooks' => 50,
            'send_instructions' => 60,
            'send_limits' => 70,
            'send_webhooks' => 80,
            'company_statements' => 90,
        ],
    ],
];
