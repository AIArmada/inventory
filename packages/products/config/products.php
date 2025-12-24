<?php

declare(strict_types=1);

return [
    /* Database */
    'database' => [
        'table_prefix' => 'product_',
        'json_column_type' => env('PRODUCTS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
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
    ],

    /* Defaults */
    'defaults' => [
        'currency' => 'MYR',
        'store_money_in_cents' => true,
    ],

    /* Features / Behavior */
    'features' => [
        'owner' => [
            'enabled' => true,
            'include_global' => false,
            'auto_assign_on_create' => true,
        ],

        'variants' => [
            'sku_pattern' => '{parent_sku}-{option_codes}',
            'max_combinations' => 1000,
        ],
    ],

    'media' => [
        'collections' => [
            'gallery' => [
                'limit' => 20,
                'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
            'hero' => [
                'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
            'icon' => [
                'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
            'banner' => [
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
            'variant_images' => [
                'limit' => 10,
                'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
        ],
        'conversions' => [
            'thumbnail' => [
                'width' => 150,
                'height' => 150,
                'sharpen' => 10,
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
            'webp-card' => [
                'width' => 400,
                'height' => 400,
            ],
        ],
    ],

    'seo' => [
        'slug_max_length' => 100,
    ],
];
