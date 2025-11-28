<?php

declare(strict_types=1);

use AIArmada\Docs\Numbering\Strategies\DefaultNumberStrategy;

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    | Preferred JSON column type for future database tables that include JSON
    | data. This mirrors other packages and defaults to the global setting.
    */
    'database' => [
        'json_column_type' => env('DOCS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => [
            'docs' => 'docs',
            'doc_templates' => 'doc_templates',
            'doc_status_histories' => 'doc_status_histories',
        ],
    ],

    'storage' => [
        'disk' => env('DOCS_STORAGE_DISK', 'local'),
        'path' => env('DOCS_STORAGE_PATH', 'docs'),
        'disks' => [],
        'paths' => [
            'invoice' => env('DOCS_STORAGE_PATH_INVOICE', 'docs/invoices'),
            'receipt' => env('DOCS_STORAGE_PATH_RECEIPT', 'docs/receipts'),
        ],
    ],

    'defaults' => [
        'currency' => env('DOCS_CURRENCY', 'MYR'),
        'tax_rate' => env('DOCS_TAX_RATE', 0),
        'due_days' => env('DOCS_DUE_DAYS', 30),
    ],

    'numbering' => [
        'format' => [
            'year_format' => env('DOCS_NUMBER_YEAR_FORMAT', 'y'),
            'separator' => env('DOCS_NUMBER_SEPARATOR', '-'),
            'suffix_length' => (int) env('DOCS_NUMBER_SUFFIX_LENGTH', 6),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Doc Types Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for different doc types supported by the package.
    | Currently supports: invoices and receipts (more can be added).
    |
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
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for PDF generation via Spatie Laravel PDF
    |
    */
    'pdf' => [
        'format' => 'a4',
        'orientation' => 'portrait',
        'margin' => [
            'top' => 10,
            'right' => 10,
            'bottom' => 10,
            'left' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Generation
    |--------------------------------------------------------------------------
    |
    | Control whether PDF files are automatically generated for documents.
    | When false, documents will be created in the database but no PDF will
    | be generated (useful for testing or when PDF generation is not needed).
    |
    */
    'generate_pdf' => env('DOCS_GENERATE_PDF', true),

    /*
    |--------------------------------------------------------------------------
    | Company Information
    |--------------------------------------------------------------------------
    |
    | Default company information to display on docs. This can be
    | overridden when creating a doc.
    |
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
