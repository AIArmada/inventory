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
        'badge_color' => 'success',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'polling_interval' => '45s',
        'date_format' => 'Y-m-d H:i:s',
        'amount_precision' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'subscriptions' => true,
        'customers' => true,
        'invoices' => true,
        'payment_methods' => true,
        'dashboard_widgets' => true,
        'dashboard' => [
            'widgets' => [
                'mrr' => true,
                'active_subscribers' => true,
                'churn_rate' => true,
                'attention_required' => true,
                'revenue_chart' => true,
                'subscription_distribution' => true,
                'trial_conversions' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing Portal
    |--------------------------------------------------------------------------
    */
    'billing' => [
        'billable_model' => null,
        'panel_id' => 'billing',
        'features' => [
            'subscriptions' => true,
            'payment_methods' => true,
            'invoices' => true,
        ],
        'invoice' => [
            'vendor_name' => null,
            'product_name' => 'Subscription',
        ],
        'redirects' => [
            'after_payment_method_added' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */
    'resources' => [
        'navigation_sort' => [
            'subscriptions' => 10,
            'customers' => 20,
            'invoices' => 30,
        ],
    ],
];
