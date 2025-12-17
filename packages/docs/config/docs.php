<?php

declare(strict_types=1);

use AIArmada\Docs\Numbering\Strategies\DefaultNumberStrategy;

$tablePrefix = env('DOCS_TABLE_PREFIX', 'docs_');

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('DOCS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => [
            'docs' => env('DOCS_TABLE', $tablePrefix . 'docs'),
            'doc_templates' => env('DOC_TEMPLATES_TABLE', $tablePrefix . 'doc_templates'),
            'doc_status_histories' => env('DOC_STATUS_HISTORIES_TABLE', $tablePrefix . 'doc_status_histories'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    |
    | Multi-tenancy support for scoping docs by owner. When enabled, docs
    | are isolated per owner using the OwnerResolverInterface binding from
    | commerce-support.
    |
    */
    'owner' => [
        'enabled' => env('DOCS_OWNER_ENABLED', false),
        'include_global' => env('DOCS_OWNER_INCLUDE_GLOBAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'currency' => env('DOCS_CURRENCY', 'MYR'),
        'tax_rate' => env('DOCS_TAX_RATE', 0),
        'due_days' => env('DOCS_DUE_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Types
    |--------------------------------------------------------------------------
    */
    'types' => [
        'invoice' => [
            'default_template' => 'doc-default',
            'numbering' => [
                'strategy' => DefaultNumberStrategy::class,
                'prefix' => 'INV',
            ],
        ],
        'receipt' => [
            'default_template' => 'doc-default',
            'numbering' => [
                'strategy' => DefaultNumberStrategy::class,
                'prefix' => 'RCP',
            ],
        ],
        'credit_note' => [
            'default_template' => 'doc-default',
            'numbering' => [
                'strategy' => DefaultNumberStrategy::class,
                'prefix' => 'CN',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Numbering
    |--------------------------------------------------------------------------
    */
    'numbering' => [
        'format' => [
            'year_format' => env('DOCS_NUMBER_YEAR_FORMAT', 'y'),
            'separator' => env('DOCS_NUMBER_SEPARATOR', '-'),
            'suffix_length' => (int) env('DOCS_NUMBER_SUFFIX_LENGTH', 6),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'disk' => env('DOCS_STORAGE_DISK', 'local'),
        'path' => env('DOCS_STORAGE_PATH', 'docs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF
    |--------------------------------------------------------------------------
    */
    'generate_pdf' => env('DOCS_GENERATE_PDF', true),

    'pdf' => [
        'format' => 'a4',
        'orientation' => 'portrait',
        'margin' => [
            'top' => 10,
            'right' => 10,
            'bottom' => 10,
            'left' => 10,
        ],
        'full_bleed' => false,
        'print_background' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Company
    |--------------------------------------------------------------------------
    */
    'company' => [
        'name' => env('DOCS_COMPANY_NAME', config('app.name')),
        'address' => env('DOCS_COMPANY_ADDRESS'),
        'city' => env('DOCS_COMPANY_CITY'),
        'state' => env('DOCS_COMPANY_STATE'),
        'postcode' => env('DOCS_COMPANY_POSTCODE'),
        'country' => env('DOCS_COMPANY_COUNTRY'),
        'phone' => env('DOCS_COMPANY_PHONE'),
        'email' => env('DOCS_COMPANY_EMAIL'),
        'website' => env('DOCS_COMPANY_WEBSITE'),
        'tax_id' => env('DOCS_COMPANY_TAX_ID'),
    ],
];
