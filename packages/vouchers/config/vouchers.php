<?php

declare(strict_types=1);

$tablePrefix = env('VOUCHERS_TABLE_PREFIX', env('COMMERCE_TABLE_PREFIX', ''));

$tables = [
    'vouchers' => $tablePrefix . 'vouchers',
    'voucher_usage' => $tablePrefix . 'voucher_usage',
    'voucher_wallets' => $tablePrefix . 'voucher_wallets',
    'voucher_assignments' => $tablePrefix . 'voucher_assignments',
    'voucher_transactions' => $tablePrefix . 'voucher_transactions',
    'campaigns' => $tablePrefix . 'voucher_campaigns',
    'campaign_variants' => $tablePrefix . 'voucher_campaign_variants',
    'campaign_events' => $tablePrefix . 'voucher_campaign_events',
    'gift_cards' => $tablePrefix . 'gift_cards',
    'gift_card_transactions' => $tablePrefix . 'gift_card_transactions',
    'voucher_fraud_signals' => $tablePrefix . 'voucher_fraud_signals',
];

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('VOUCHERS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => $tables,
    ],

    'table_names' => $tables,

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'default_currency' => 'MYR',

    'code' => [
        'auto_uppercase' => env('VOUCHERS_AUTO_UPPERCASE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Integration
    |--------------------------------------------------------------------------
    */
    'cart' => [
        'max_vouchers_per_cart' => env('VOUCHERS_MAX_PER_CART', 1), // 1=single voucher, >1=stacking enabled, -1=unlimited
        'replace_when_max_reached' => env('VOUCHERS_REPLACE_WHEN_MAX', true),
        'condition_order' => env('VOUCHERS_CONDITION_ORDER', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stacking Policies
    |--------------------------------------------------------------------------
    */
    'stacking' => [
        'mode' => env('VOUCHERS_STACKING_MODE', 'sequential'), // none, sequential, parallel, best_deal, custom

        'rules' => [
            [
                'type' => 'max_vouchers',
                'value' => (int) env('VOUCHERS_MAX_PER_CART', 3),
            ],
            [
                'type' => 'max_discount_percentage',
                'value' => (int) env('VOUCHERS_MAX_DISCOUNT_PCT', 50),
            ],
            [
                'type' => 'type_restriction',
                'max_per_type' => [
                    'percentage' => 1,
                    'fixed' => 2,
                    'free_shipping' => 1,
                ],
            ],
        ],

        'auto_optimize' => env('VOUCHERS_AUTO_OPTIMIZE', false),
        'auto_replace' => env('VOUCHERS_AUTO_REPLACE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'check_user_limit' => env('VOUCHERS_CHECK_USER_LIMIT', true),
        'check_global_limit' => env('VOUCHERS_CHECK_GLOBAL_LIMIT', true),
        'check_min_cart_value' => env('VOUCHERS_CHECK_MIN_CART_VALUE', true),
        'check_targeting' => env('VOUCHERS_CHECK_TARGETING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking
    |--------------------------------------------------------------------------
    */
    'tracking' => [
        'track_applications' => env('VOUCHERS_TRACK_APPLICATIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    */
    'owner' => [
        'enabled' => env('VOUCHERS_OWNER_ENABLED', false),
        'include_global' => env('VOUCHERS_OWNER_INCLUDE_GLOBAL', true),
        'auto_assign_on_create' => env('VOUCHERS_OWNER_AUTO_ASSIGN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redemption
    |--------------------------------------------------------------------------
    */
    'redemption' => [
        'manual_requires_flag' => env('VOUCHERS_MANUAL_REQUIRES_FLAG', true),
        'manual_channel' => 'manual',
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Optimization
    |--------------------------------------------------------------------------
    |
    | Configure AI-powered optimization features. By default, rule-based
    | heuristics are used. These can be swapped for ML implementations
    | (AWS SageMaker, TensorFlow, etc.) by rebinding the interfaces
    | in your AppServiceProvider.
    |
    */
    'ai' => [
        'enabled' => env('VOUCHERS_AI_ENABLED', true),

        // Conversion prediction settings
        'conversion' => [
            'high_probability_threshold' => 0.7,
            'low_probability_threshold' => 0.3,
            'min_confidence' => 0.5,
        ],

        // Abandonment prediction settings
        'abandonment' => [
            'high_risk_threshold' => 0.6,
            'critical_risk_threshold' => 0.8,
            'cart_age_weight' => 1.0,
        ],

        // Discount optimization settings
        'discount' => [
            'min_roi' => 1.0,
            'max_discount_percent' => 50,
            'discount_levels' => [0, 5, 10, 15, 20, 25, 30],
        ],

        // Voucher matching settings
        'matching' => [
            'min_match_score' => 0.3,
            'strong_match_threshold' => 0.7,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Integrations
    |--------------------------------------------------------------------------
    |
    | Configure integrations with other AIArmada packages when installed.
    |
    */

    'integrations' => [
        /*
        |----------------------------------------------------------------------
        | Affiliates Integration (aiarmada/affiliates)
        |----------------------------------------------------------------------
        |
        | When the affiliates package is installed, vouchers can be directly
               | When enabled, vouchers are automatically scoped to the current owner.
               | The OwnerResolverInterface binding is provided by commerce-support.
        */
        'affiliates' => [
            'enabled' => env('VOUCHERS_AFFILIATES_ENABLED', true),

            // Auto-create voucher when affiliate is created
            'auto_create_voucher' => env('VOUCHERS_AFFILIATES_AUTO_CREATE', false),

            // Create voucher when affiliate is activated (recommended)
            'create_on_activation' => env('VOUCHERS_AFFILIATES_CREATE_ON_ACTIVATION', true),

            // Set the created voucher as affiliate's default_voucher_code
            'set_default_voucher_code' => env('VOUCHERS_AFFILIATES_SET_DEFAULT', true),

            // Voucher code format: prefix_code|code_only|prefix_random
            'code_format' => env('VOUCHERS_AFFILIATES_CODE_FORMAT', 'prefix_code'),
            'code_prefix' => env('VOUCHERS_AFFILIATES_CODE_PREFIX', 'REF'),

            // Default voucher settings for auto-created affiliate vouchers
            'voucher_defaults' => [
                'type' => env('VOUCHERS_AFFILIATES_DEFAULT_TYPE', 'percentage'),
                'value' => env('VOUCHERS_AFFILIATES_DEFAULT_VALUE', 1000), // 10% in basis points
                'currency' => env('VOUCHERS_AFFILIATES_DEFAULT_CURRENCY'),
                'status' => 'active',
            ],
        ],
    ],
];
