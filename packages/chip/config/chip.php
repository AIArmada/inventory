<?php

declare(strict_types=1);

$webhookKeysEnv = env('CHIP_WEBHOOK_PUBLIC_KEYS');
$webhookKeys = [];
if ($webhookKeysEnv !== null) {
    $decoded = json_decode($webhookKeysEnv, true);
    if (is_array($decoded)) {
        $webhookKeys = $decoded;
    }
}

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => env('CHIP_TABLE_PREFIX', 'chip_'),
        'json_column_type' => env('CHIP_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credentials / API
    |--------------------------------------------------------------------------
    */
    'environment' => env('CHIP_ENVIRONMENT', 'sandbox'),

    'collect' => [
        'base_url' => env('CHIP_COLLECT_BASE_URL', 'https://gate.chip-in.asia/api/v1/'),
        'api_key' => env('CHIP_COLLECT_API_KEY'),
        'brand_id' => env('CHIP_COLLECT_BRAND_ID'),
    ],
    'send' => [
        'base_url' => [
            'sandbox' => env('CHIP_SEND_SANDBOX_URL', 'https://staging-api.chip-in.asia/api'),
            'production' => env('CHIP_SEND_PRODUCTION_URL', 'https://api.chip-in.asia/api'),
        ],
        'api_key' => env('CHIP_SEND_API_KEY'),
        'api_secret' => env('CHIP_SEND_API_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'currency' => env('CHIP_DEFAULT_CURRENCY', 'MYR'),
        'creator_agent' => env('CHIP_CREATOR_AGENT', 'AIArmada/Chip'),
        'platform' => env('CHIP_PLATFORM', 'api'),
        'payment_method_whitelist' => env('CHIP_PAYMENT_METHOD_WHITELIST', ''),
        'success_redirect' => env('CHIP_SUCCESS_REDIRECT'),
        'failure_redirect' => env('CHIP_FAILURE_REDIRECT'),
        'send_receipt' => env('CHIP_SEND_RECEIPT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    |
    | When enabled, purchases are automatically scoped to the current owner.
    | The OwnerResolverInterface binding is provided by commerce-support.
    |
    */
    'owner' => [
        'enabled' => env('CHIP_OWNER_ENABLED', false),
        'include_global' => env('CHIP_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => env('CHIP_OWNER_AUTO_ASSIGN', true),
        'webhook_brand_id_map' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    */
    'integrations' => [
        // Docs package integration (auto-generate invoices/credit notes)
        'docs' => [
            'enabled' => env('CHIP_DOCS_INTEGRATION_ENABLED', true),
            'auto_generate_invoice' => env('CHIP_DOCS_AUTO_INVOICE', true),
            'auto_generate_credit_note' => env('CHIP_DOCS_AUTO_CREDIT_NOTE', true),
            'paid_doc_type' => env('CHIP_DOCS_PAID_TYPE', 'invoice'),
            'refund_doc_type' => env('CHIP_DOCS_REFUND_TYPE', 'credit_note'),
            'generate_pdf' => env('CHIP_DOCS_GENERATE_PDF', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => env('CHIP_HTTP_TIMEOUT', 30),
        'retry' => [
            'attempts' => env('CHIP_HTTP_RETRY_ATTEMPTS', 3),
            'delay' => env('CHIP_HTTP_RETRY_DELAY', 1000),
        ],
        'rate_limit' => [
            'enabled' => env('CHIP_RATE_LIMIT_ENABLED', true),
            'max_attempts' => env('CHIP_RATE_LIMIT_MAX', 60),
            'decay_seconds' => env('CHIP_RATE_LIMIT_DECAY', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'enabled' => env('CHIP_WEBHOOKS_ENABLED', true),
        'route' => env('CHIP_WEBHOOK_ROUTE', '/chip/webhook'),
        'middleware' => ['api'],
        'company_public_key' => env('CHIP_COMPANY_PUBLIC_KEY'),
        'webhook_keys' => $webhookKeys,
        'verify_signature' => env('CHIP_WEBHOOK_VERIFY_SIGNATURE', true),
        'log_payloads' => env('CHIP_WEBHOOK_LOG_PAYLOADS', false),
        'store_webhooks' => env('CHIP_WEBHOOK_STORE', true),
        'deduplication' => env('CHIP_WEBHOOK_DEDUPLICATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'prefix' => env('CHIP_CACHE_PREFIX', 'chip:'),
        'default_ttl' => env('CHIP_CACHE_TTL', 3600),
        'ttl' => [
            'public_key' => env('CHIP_CACHE_PUBLIC_KEY_TTL', 86400),
            'payment_methods' => env('CHIP_CACHE_PAYMENT_METHODS_TTL', 3600),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('CHIP_LOGGING_ENABLED', env('APP_DEBUG', false)),
        'channel' => env('CHIP_LOGGING_CHANNEL', 'stack'),
        'mask_sensitive_data' => env('CHIP_LOGGING_MASK_SENSITIVE', true),
        'log_requests' => env('CHIP_LOG_REQUESTS', true),
        'log_responses' => env('CHIP_LOG_RESPONSES', true),
        'sensitive_fields' => [], // Additional fields to mask (merged with defaults)
    ],
];
