<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'tax_zones' => 'tax_zones',
        'tax_rates' => 'tax_rates',
        'tax_classes' => 'tax_classes',
        'tax_exemptions' => 'tax_exemptions',
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
    | When enabled, tax data is automatically scoped to the owner.
    */
    'owner' => [
        'enabled' => env('TAX_OWNER_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Tax Behavior
    |--------------------------------------------------------------------------
    */
    'prices_include_tax' => env('TAX_PRICES_INCLUDE_TAX', false),
    'calculate_tax_on_shipping' => env('TAX_ON_SHIPPING', true),
    'round_at_subtotal' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Tax Class
    |--------------------------------------------------------------------------
    */
    'default_tax_class' => 'standard',

    /*
    |--------------------------------------------------------------------------
    | Tax Classes
    |--------------------------------------------------------------------------
    | Predefined tax classes for different product types.
    */
    'classes' => [
        'standard' => [
            'name' => 'Standard Rate',
            'description' => 'Standard tax rate for most products',
        ],
        'reduced' => [
            'name' => 'Reduced Rate',
            'description' => 'Reduced rate for essential goods',
        ],
        'zero' => [
            'name' => 'Zero Rate',
            'description' => 'Zero-rated goods (exports, etc.)',
        ],
        'exempt' => [
            'name' => 'Tax Exempt',
            'description' => 'Items exempt from tax',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Display Settings
    |--------------------------------------------------------------------------
    */
    'display' => [
        'price_display_mode' => 'excluding_tax', // 'including_tax', 'excluding_tax', 'both'
        'cart_display_mode' => 'excluding_tax',
        'checkout_display_mode' => 'both',
    ],

    /*
    |--------------------------------------------------------------------------
    | Zone Resolution
    |--------------------------------------------------------------------------
    | How to determine the tax zone for a customer.
    */
    'zone_resolution' => [
        'use_customer_address' => true,
        'address_priority' => 'shipping', // 'shipping', 'billing'
        'unknown_zone_behavior' => 'default', // 'default', 'zero', 'error'
        'fallback_zone_id' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Exemptions
    |--------------------------------------------------------------------------
    */
    'exemptions' => [
        'enabled' => true,
        'require_document' => true,
        'document_types' => ['business_license', 'tax_exempt_certificate'],
        'auto_approve' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Malaysia-Specific Settings
    |--------------------------------------------------------------------------
    */
    'malaysia' => [
        'sst_rate' => 6, // 6% Sales & Service Tax
        'service_tax_rate' => 6,
        'sales_tax_rate' => 10,
        'exempt_categories' => ['food', 'education', 'healthcare'],
    ],
];
