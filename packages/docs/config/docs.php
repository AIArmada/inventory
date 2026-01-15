<?php

declare(strict_types=1);

use AIArmada\Docs\Numbering\Strategies\DefaultNumberStrategy;

$tablePrefix = env('DOCS_TABLE_PREFIX', 'docs_');

return [
    /* Database */
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('DOCS_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => [
            'docs' => env('DOCS_TABLE', $tablePrefix . 'docs'),
            'doc_templates' => env('DOC_TEMPLATES_TABLE', $tablePrefix . 'doc_templates'),
            'doc_status_histories' => env('DOC_STATUS_HISTORIES_TABLE', $tablePrefix . 'doc_status_histories'),
            'doc_payments' => env('DOC_PAYMENTS_TABLE', $tablePrefix . 'payments'),
            'doc_email_templates' => env('DOC_EMAIL_TEMPLATES_TABLE', $tablePrefix . 'email_templates'),
            'doc_emails' => env('DOC_EMAILS_TABLE', $tablePrefix . 'emails'),
            'doc_versions' => env('DOC_VERSIONS_TABLE', $tablePrefix . 'versions'),
            'doc_approvals' => env('DOC_APPROVALS_TABLE', $tablePrefix . 'approvals'),
            'doc_einvoice_submissions' => env('DOC_EINVOICE_SUBMISSIONS_TABLE', $tablePrefix . 'einvoice_submissions'),
            'doc_sequences' => env('DOC_SEQUENCES_TABLE', $tablePrefix . 'sequences'),
            'sequence_numbers' => env('DOC_SEQUENCE_NUMBERS_TABLE', $tablePrefix . 'sequence_numbers'),
            'workflows' => env('DOC_WORKFLOWS_TABLE', $tablePrefix . 'workflows'),
            'workflow_steps' => env('DOC_WORKFLOW_STEPS_TABLE', $tablePrefix . 'workflow_steps'),
        ],
    ],

    /* Defaults */
    'defaults' => [
        'currency' => env('DOCS_CURRENCY', 'MYR'),
        'tax_rate' => env('DOCS_TAX_RATE', 0),
        'due_days' => env('DOCS_DUE_DAYS', 30),
    ],

    /* Payment Methods */
    'payment_methods' => [
        'bank_transfer' => 'Bank Transfer',
        'cash' => 'Cash',
        'credit_card' => 'Credit Card',
        'check' => 'Check',
        'e_wallet' => 'E-Wallet',
        'other' => 'Other',
    ],

    /* Features */
    'owner' => [
        'enabled' => env('DOCS_OWNER_ENABLED', false),
        'include_global' => env('DOCS_OWNER_INCLUDE_GLOBAL', false),
    ],

    /* Email */
    'email' => [
        'queue_enabled' => env('DOCS_EMAIL_QUEUE_ENABLED', true),
        'queue' => env('DOCS_EMAIL_QUEUE', 'default'),
        'attach_pdf' => env('DOCS_EMAIL_ATTACH_PDF', true),
        'from_address' => env('DOCS_EMAIL_FROM_ADDRESS'),
        'from_name' => env('DOCS_EMAIL_FROM_NAME'),
        'tracking' => [
            'enabled' => env('DOCS_EMAIL_TRACKING_ENABLED', true),
        ],
    ],

    /* Integrations */

    /* HTTP */

    /* Webhooks */

    /* Cache */

    /* Logging */

    /* Document Types */
    'types' => [
        'invoice' => [
            'default_template' => 'doc-default',
            'numbering' => [
                'strategy' => DefaultNumberStrategy::class,
                'prefix' => 'INV',
            ],
        ],
        'quotation' => [
            'default_template' => 'doc-default',
            'numbering' => [
                'strategy' => DefaultNumberStrategy::class,
                'prefix' => 'QUO',
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
        'delivery_note' => [
            'default_template' => 'doc-default',
            'numbering' => [
                'strategy' => DefaultNumberStrategy::class,
                'prefix' => 'DN',
            ],
        ],
        'proforma_invoice' => [
            'default_template' => 'doc-default',
            'numbering' => [
                'strategy' => DefaultNumberStrategy::class,
                'prefix' => 'PI',
            ],
        ],
    ],

    /* Numbering */
    'numbering' => [
        'format' => [
            'year_format' => env('DOCS_NUMBER_YEAR_FORMAT', 'y'),
            'separator' => env('DOCS_NUMBER_SEPARATOR', '-'),
            'suffix_length' => (int) env('DOCS_NUMBER_SUFFIX_LENGTH', 6),
        ],
    ],

    /* Storage */
    'storage' => [
        'disk' => env('DOCS_STORAGE_DISK', 'local'),
        'path' => env('DOCS_STORAGE_PATH', 'docs'),
    ],

    /* PDF */
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

    /* Company */
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
