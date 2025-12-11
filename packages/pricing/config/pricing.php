<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'prices' => 'prices',
        'price_lists' => 'price_lists',
        'price_tiers' => 'price_tiers',
        'price_rules' => 'price_rules',
        'promotions' => 'promotions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database JSON Column Type
    |--------------------------------------------------------------------------
    */
    'json_column_type' => 'json',

    /*
    |--------------------------------------------------------------------------
    | Owner (Multi-tenancy)
    |--------------------------------------------------------------------------
    | When enabled, pricing data is automatically scoped to the owner.
    */
    'owner' => [
        'enabled' => env('PRICING_OWNER_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    */
    'currency' => [
        'default' => env('COMMERCE_CURRENCY', 'MYR'),
        'enabled' => ['MYR', 'USD', 'SGD'],
        'display' => 'symbol', // 'symbol', 'code', 'both'
        'decimal_places' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rounding Settings
    |--------------------------------------------------------------------------
    */
    'rounding' => [
        'mode' => 'nearest', // 'up', 'down', 'nearest'
        'precision' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Price Display
    |--------------------------------------------------------------------------
    */
    'display' => [
        'show_original_price' => true,
        'show_savings_amount' => true,
        'show_savings_percentage' => true,
        'show_tier_breakdown' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tiered Pricing
    |--------------------------------------------------------------------------
    */
    'tiered_pricing' => [
        'enabled' => true,
        'calculation_method' => 'tier', // 'tier' (whole qty at tier rate) or 'graduated' (incremental)
    ],

    /*
    |--------------------------------------------------------------------------
    | Promotional Pricing
    |--------------------------------------------------------------------------
    */
    'promotional' => [
        'enabled' => true,
        'show_countdown' => true,
        'hide_expired' => true,
        'max_flash_sale_items' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer Segments
    |--------------------------------------------------------------------------
    | Pricing can vary by customer segment for B2B/wholesale pricing.
    */
    'segments' => [
        'enabled' => true,
        'default_segment' => 'retail',
    ],

    /*
    |--------------------------------------------------------------------------
    | Price Rules Priority
    |--------------------------------------------------------------------------
    | Order in which pricing rules are applied.
    */
    'rule_priority' => [
        'customer_specific',  // 1. Customer-specific price
        'segment',            // 2. Customer segment price
        'tier',               // 3. Quantity tier price
        'promotion',          // 4. Active promotion
        'price_list',         // 5. Price list
        'base',               // 6. Base price
    ],
];
