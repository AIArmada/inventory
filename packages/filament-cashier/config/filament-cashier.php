<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'Billing',
        'sort' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'polling_interval' => '45s',
        'date_format' => 'M d, Y',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateways
    |--------------------------------------------------------------------------
    */
    'gateways' => [
        'stripe' => [
            'label' => 'Stripe',
            'icon' => 'heroicon-o-credit-card',
            'color' => 'indigo',
            'dashboard_url' => 'https://dashboard.stripe.com',
        ],
        'chip' => [
            'label' => 'CHIP',
            'icon' => 'heroicon-o-cube',
            'color' => 'emerald',
            'dashboard_url' => 'https://gate.chip-in.asia',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'dashboard' => true,
        'subscriptions' => true,
        'invoices' => true,
        'gateway_management' => false,
        'customer_portal' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */
    'resources' => [
        'navigation_sort' => [
            'subscriptions' => 10,
            'invoices' => 20,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Conversion
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'base' => 'USD',
        'display_converted' => false,
        'conversion_rates' => [
            'MYR' => 4.70,
            'USD' => 1.00,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer Portal (Billing Panel)
    |--------------------------------------------------------------------------
    */
    'billing_portal' => [
        'enabled' => false,
        'panel_id' => 'billing',
        'path' => 'billing',
        'brand_name' => env('FILAMENT_CASHIER_BRAND_NAME', 'Billing Portal'),
        'primary_color' => env('FILAMENT_CASHIER_PRIMARY_COLOR', '#6366f1'),
        'auth_guard' => 'web',
        'features' => [
            'subscriptions' => true,
            'payment_methods' => true,
            'invoices' => true,
            'gateway_switching' => false,
        ],
    ],
];
