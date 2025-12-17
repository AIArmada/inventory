<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by the products package.
    |
    */
    'tables' => [
        'products' => 'products',
        'variants' => 'product_variants',
        'options' => 'product_options',
        'option_values' => 'product_option_values',
        'variant_options' => 'product_variant_options',
        'categories' => 'product_categories',
        'category_product' => 'category_product',
        'collections' => 'product_collections',
        'collection_product' => 'collection_product',
        'attributes' => 'product_attributes',
        'attribute_groups' => 'product_attribute_groups',
        'attribute_values' => 'product_attribute_values',
        'attribute_sets' => 'product_attribute_sets',
        'attribute_attribute_group' => 'product_attribute_attribute_group',
        'attribute_attribute_set' => 'product_attribute_attribute_set',
        'attribute_group_attribute_set' => 'product_attribute_group_attribute_set',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database JSON Column Type
    |--------------------------------------------------------------------------
    */
    'json_column_type' => 'json',

    /*
    |--------------------------------------------------------------------------
    | Owner Scoping
    |--------------------------------------------------------------------------
    */
    'owner' => [
        'enabled' => true,
        'include_global' => true,
        'auto_assign_on_create' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Types
    |--------------------------------------------------------------------------
    |
    | Define the available product types. Each type may have different
    | behaviors for variants, pricing, and fulfillment.
    |
    */
    'types' => [
        'simple' => [
            'label' => 'Simple Product',
            'has_variants' => false,
            'description' => 'A standalone product with no options',
        ],
        'configurable' => [
            'label' => 'Configurable Product',
            'has_variants' => true,
            'description' => 'A product with multiple options (size, color)',
        ],
        'bundle' => [
            'label' => 'Bundle',
            'has_variants' => false,
            'description' => 'A collection of products sold together',
        ],
        'digital' => [
            'label' => 'Digital Product',
            'has_variants' => false,
            'description' => 'A downloadable or virtual product',
        ],
        'subscription' => [
            'label' => 'Subscription',
            'has_variants' => false,
            'description' => 'A recurring billing product',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Statuses
    |--------------------------------------------------------------------------
    |
    | Define the available product statuses and their behavior.
    |
    */
    'statuses' => [
        'draft' => [
            'label' => 'Draft',
            'color' => 'gray',
            'visible' => false,
            'purchasable' => false,
        ],
        'active' => [
            'label' => 'Active',
            'color' => 'success',
            'visible' => true,
            'purchasable' => true,
        ],
        'disabled' => [
            'label' => 'Disabled',
            'color' => 'warning',
            'visible' => false,
            'purchasable' => false,
        ],
        'archived' => [
            'label' => 'Archived',
            'color' => 'danger',
            'visible' => false,
            'purchasable' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Default currency for products.
    |
    */
    'currency' => [
        'default' => 'MYR',
        'store_in_cents' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Collections
    |--------------------------------------------------------------------------
    |
    | Configure media collection settings for product images and files.
    |
    */
    'media' => [
        'collections' => [
            'gallery' => [
                'limit' => 20,
                'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
            'hero' => [
                'limit' => 1,
                'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
            'videos' => [
                'limit' => 5,
                'mimes' => ['video/mp4', 'video/webm'],
            ],
            'documents' => [
                'limit' => 10,
                'mimes' => ['application/pdf'],
            ],
        ],
        'conversions' => [
            'thumbnail' => [
                'width' => 150,
                'height' => 150,
            ],
            'card' => [
                'width' => 400,
                'height' => 400,
            ],
            'detail' => [
                'width' => 800,
                'height' => 800,
            ],
            'zoom' => [
                'width' => 1600,
                'height' => 1600,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO Defaults
    |--------------------------------------------------------------------------
    |
    | Default SEO settings for products.
    |
    */
    'seo' => [
        'meta_title_suffix' => null, // e.g., " | My Store"
        'meta_description_length' => 160,
        'slug_max_length' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Category Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the category system.
    |
    */
    'categories' => [
        'max_depth' => null, // null = unlimited
        'root_category_required' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Variant Generation
    |--------------------------------------------------------------------------
    |
    | Settings for automatic variant generation.
    |
    */
    'variants' => [
        'auto_generate' => true,
        'sku_pattern' => '{parent_sku}-{option_codes}',
        'max_combinations' => 1000, // Safety limit
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Logging
    |--------------------------------------------------------------------------
    |
    | Configure which attributes should be logged for activity tracking.
    |
    */
    'activity' => [
        'log_name' => 'products',
        'loggable_attributes' => [
            'name',
            'sku',
            'price',
            'status',
            'visibility',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    |
    | Auto-detection and integration with other packages.
    |
    */
    'integrations' => [
        'inventory' => [
            'enabled' => true,
        ],
        'pricing' => [
            'enabled' => true,
        ],
        'tax' => [
            'enabled' => true,
        ],
        'cashier' => [
            'enabled' => true,
            'sync_to_stripe' => false,
        ],
    ],
];
