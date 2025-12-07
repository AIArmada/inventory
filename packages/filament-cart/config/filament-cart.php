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
            'snapshots' => 'cart_snapshots',
            'snapshot_items' => 'cart_snapshot_items',
            'snapshot_conditions' => 'cart_snapshot_conditions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'E-Commerce',

    'resources' => [
        'navigation_sort' => [
            'carts' => 30,
            'cart_items' => 31,
            'cart_conditions' => 32,
            'conditions' => 33,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'polling_interval' => '30s',

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'global_conditions' => true,
        'abandonment_tracking' => true,
        'fraud_detection' => true,
        'collaborative_carts' => true,
        'ai_recovery' => true,
    ],

    'dynamic_rules_factory' => AIArmada\Cart\Services\BuiltInRulesFactory::class,

    /*
    |--------------------------------------------------------------------------
    | Dashboard Widgets
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'stats_overview' => true,
        'abandoned_carts' => true,
        'fraud_detection' => true,
        'recovery_optimizer' => true,
        'collaborative_carts' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI/Analytics Settings
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'max_recovery_attempts' => 3,
        'abandonment_window_days' => 7,
        'high_value_threshold_cents' => 10000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fraud Detection Settings
    |--------------------------------------------------------------------------
    */
    'fraud' => [
        'show_high_risk_only' => true,
        'alert_threshold' => 0.6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronization
    |--------------------------------------------------------------------------
    */
    'synchronization' => [
        'queue_sync' => false,
        'queue_connection' => 'default',
        'queue_name' => 'cart-sync',
    ],
];
