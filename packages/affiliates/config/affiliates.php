<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;

$tablePrefix = env('AFFILIATES_TABLE_PREFIX', 'affiliate_');
$tables = [
    'affiliates' => $tablePrefix . 'affiliates',
    'attributions' => $tablePrefix . 'attributions',
    'conversions' => $tablePrefix . 'conversions',
    'payouts' => $tablePrefix . 'payouts',
    'payout_events' => $tablePrefix . 'payout_events',
    'touchpoints' => $tablePrefix . 'touchpoints',
    'ranks' => $tablePrefix . 'ranks',
    'network' => $tablePrefix . 'network',
    'rank_histories' => $tablePrefix . 'rank_histories',
    'daily_stats' => $tablePrefix . 'daily_stats',
    'fraud_signals' => $tablePrefix . 'fraud_signals',
    'balances' => $tablePrefix . 'balances',
    'payout_methods' => $tablePrefix . 'payout_methods',
    'payout_holds' => $tablePrefix . 'payout_holds',
    'programs' => $tablePrefix . 'programs',
    'program_tiers' => $tablePrefix . 'program_tiers',
    'program_memberships' => $tablePrefix . 'program_memberships',
    'program_creatives' => $tablePrefix . 'program_creatives',
    'links' => $tablePrefix . 'links',
    'commission_rules' => $tablePrefix . 'commission_rules',
    'volume_tiers' => $tablePrefix . 'volume_tiers',
    'commission_promotions' => $tablePrefix . 'commission_promotions',
];

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('AFFILIATES_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => $tables,
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Commission calculations store amounts in the smallest unit (cents).
    | Configure the fallback ISO currency and rounding precision (basis points
    | for percentage commissions).
    |
    */

    'currency' => [
        'default' => env('AFFILIATES_DEFAULT_CURRENCY', 'USD'),
        'percentage_scale' => env('AFFILIATES_PERCENTAGE_SCALE', 100), // basis points (100 = 1%)
    ],

    'table_names' => $tables,

    /*
    |--------------------------------------------------------------------------
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    |
    | Register a resolver that returns the current owner (merchant, tenant, etc).
    | When enabled, affiliates/attributions/conversions are automatically scoped.
    |
    */

    'owner' => [
        'enabled' => env('AFFILIATES_OWNER_ENABLED', false),
        'resolver' => NullOwnerResolver::class,
        'auto_assign_on_create' => env('AFFILIATES_OWNER_AUTO_ASSIGN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Integration
    |--------------------------------------------------------------------------
    |
    | Configure how cart metadata is stored and whether the package should
    | decorate the cart manager automatically.
    |
    */

    'cart' => [
        'metadata_key' => env('AFFILIATES_CART_METADATA_KEY', 'affiliate'),
        'register_manager_proxy' => env('AFFILIATES_CART_PROXY', true),
        'persist_metadata' => env('AFFILIATES_CART_PERSIST_METADATA', true),
        // Enable affiliate customer discounts as cart conditions
        'customer_discounts_enabled' => env('AFFILIATES_CUSTOMER_DISCOUNTS_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookie Tracking
    |--------------------------------------------------------------------------
    |
    | Persist affiliate visits even before a cart exists by dropping a tracking
    | cookie. Configure the cookie name, lifetime, and auto-registration of the
    | middleware that captures affiliate query parameters.
    |
    */

    'cookies' => [
        'enabled' => env('AFFILIATES_COOKIES_ENABLED', true),
        'name' => env('AFFILIATES_COOKIE_NAME', 'affiliate_session'),
        'ttl_minutes' => env('AFFILIATES_COOKIE_TTL_MINUTES', 60 * 24 * 30),
        'path' => env('AFFILIATES_COOKIE_PATH', '/'),
        'domain' => env('AFFILIATES_COOKIE_DOMAIN'),
        'secure' => env('AFFILIATES_COOKIE_SECURE'),
        'http_only' => env('AFFILIATES_COOKIE_HTTP_ONLY', true),
        'same_site' => env('AFFILIATES_COOKIE_SAME_SITE', 'lax'),
        'query_parameters' => ['aff', 'affiliate', 'ref', 'referral'],
        'auto_register_middleware' => env('AFFILIATES_COOKIES_AUTO_MIDDLEWARE', true),
        'respect_dnt' => env('AFFILIATES_COOKIES_RESPECT_DNT', false),
        'require_consent' => env('AFFILIATES_COOKIES_REQUIRE_CONSENT', false),
        'consent_cookie' => env('AFFILIATES_COOKIES_CONSENT_COOKIE', 'affiliate_consent'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voucher Integration
    |--------------------------------------------------------------------------
    |
    | When aiarmada/vouchers is installed we inspect voucher metadata for an
    | affiliate code. Add any dot-notation keys you want the listener to check.
    |
    */

    'integrations' => [
        'vouchers' => [
            'attach_on_apply' => env('AFFILIATES_ATTACH_ON_VOUCHER', true),
            'metadata_keys' => [
                'affiliate_code',
                'affiliate.code',
                'metadata.affiliate_code',
            ],
            'match_default_voucher_code' => env('AFFILIATES_MATCH_DEFAULT_VOUCHER', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Commission Settings
    |--------------------------------------------------------------------------
    */

    'commissions' => [
        'auto_approve' => env('AFFILIATES_AUTO_APPROVE', false),
        'default_status' => 'pending',
    ],

    'payouts' => [
        'currency' => env('AFFILIATES_PAYOUT_CURRENCY', env('AFFILIATES_DEFAULT_CURRENCY', 'USD')),
        'reference_prefix' => env('AFFILIATES_PAYOUT_REF_PREFIX', 'PO-'),
        'multi_level' => [
            'enabled' => env('AFFILIATES_MULTI_LEVEL_ENABLED', false),
            'levels' => array_filter(array_map('floatval', explode(',', (string) env('AFFILIATES_MULTI_LEVEL_LEVELS', '0.1,0.05')))), // percentages of commission (0.1 = 10%)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking Defaults
    |--------------------------------------------------------------------------
    */

    'tracking' => [
        'attribution_ttl_days' => env('AFFILIATES_ATTRIBUTION_TTL_DAYS', 30),
        'max_attributions_per_identifier' => env('AFFILIATES_ATTRIBUTION_MAX', 5),
        'block_self_referral' => env('AFFILIATES_BLOCK_SELF_REFERRAL', false),
        'ip_rate_limit' => [
            'enabled' => env('AFFILIATES_IP_RATE_LIMIT_ENABLED', false),
            'max' => env('AFFILIATES_IP_RATE_LIMIT_MAX', 20),
            'decay_minutes' => env('AFFILIATES_IP_RATE_LIMIT_DECAY', 30),
        ],
        'attribution_model' => env('AFFILIATES_ATTRIBUTION_MODEL', 'last_touch'), // last_touch, first_touch, linear
        'fingerprint' => [
            'enabled' => env('AFFILIATES_FINGERPRINT_ENABLED', false),
            'block_duplicates' => env('AFFILIATES_FINGERPRINT_BLOCK_DUPLICATES', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */

    'events' => [
        'dispatch_attributed' => env('AFFILIATES_EVENT_ATTRIBUTED', true),
        'dispatch_conversion' => env('AFFILIATES_EVENT_CONVERSION', true),
        'dispatch_webhooks' => env('AFFILIATES_EVENT_WEBHOOKS', false),
    ],

    /*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
*/

    'webhooks' => [
        'endpoints' => [
            'attribution' => explode(',', (string) env('AFFILIATES_WEBHOOKS_ATTRIBUTION', '')),
            'conversion' => explode(',', (string) env('AFFILIATES_WEBHOOKS_CONVERSION', '')),
            'payout' => explode(',', (string) env('AFFILIATES_WEBHOOKS_PAYOUT', '')),
        ],
        'headers' => [
            'X-Affiliates-Signature' => env('AFFILIATES_WEBHOOKS_SIGNATURE'),
        ],
    ],

    /*
|--------------------------------------------------------------------------
| Links
|--------------------------------------------------------------------------
*/

    'links' => [
        'signing_key' => env('AFFILIATES_LINK_SIGNING_KEY', env('APP_KEY')),
        'default_ttl_minutes' => env('AFFILIATES_LINK_TTL', 60 * 24 * 7),
        'parameter' => env('AFFILIATES_LINK_PARAM', 'aff'),
        'allowed_hosts' => array_filter(explode(',', (string) env('AFFILIATES_LINK_ALLOWED_HOSTS', ''))),
    ],

    /*
|--------------------------------------------------------------------------
| Public API
|--------------------------------------------------------------------------
*/

    'api' => [
        'enabled' => env('AFFILIATES_API_ENABLED', false),
        'prefix' => env('AFFILIATES_API_PREFIX', 'api/affiliates'),
        'middleware' => ['api', 'throttle:60,1'],
        'auth' => env('AFFILIATES_API_AUTH', 'token'), // token | none
        'token' => env('AFFILIATES_API_TOKEN'), // static token for simple setups
    ],

    /*
    |--------------------------------------------------------------------------
    | Fraud Detection
    |--------------------------------------------------------------------------
    */

    'fraud' => [
        'enabled' => env('AFFILIATES_FRAUD_ENABLED', true),
        'blocking_threshold' => env('AFFILIATES_FRAUD_BLOCK_THRESHOLD', 100),

        'velocity' => [
            'enabled' => env('AFFILIATES_FRAUD_VELOCITY_ENABLED', true),
            'max_clicks_per_hour' => env('AFFILIATES_FRAUD_MAX_CLICKS_HOUR', 100),
            'max_conversions_per_day' => env('AFFILIATES_FRAUD_MAX_CONVERSIONS_DAY', 50),
        ],

        'anomaly' => [
            'geo' => [
                'enabled' => env('AFFILIATES_FRAUD_GEO_ENABLED', false),
                'max_speed_kmh' => env('AFFILIATES_FRAUD_MAX_SPEED_KMH', 500),
            ],
            'conversion_time' => [
                'min_seconds' => env('AFFILIATES_FRAUD_MIN_CONVERSION_SECONDS', 5),
                'max_hours' => env('AFFILIATES_FRAUD_MAX_CONVERSION_HOURS', 720),
            ],
        ],

        'alerts' => [
            'enabled' => env('AFFILIATES_FRAUD_ALERTS_ENABLED', false),
            'channels' => ['mail'],
            'recipients' => array_filter(explode(',', (string) env('AFFILIATES_FRAUD_ALERT_RECIPIENTS', ''))),
            'threshold' => 'high',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Network / MLM Settings
    |--------------------------------------------------------------------------
    */

    'network' => [
        'enabled' => env('AFFILIATES_NETWORK_ENABLED', false),
        'max_depth' => env('AFFILIATES_NETWORK_MAX_DEPTH', 10),
        'compression' => env('AFFILIATES_NETWORK_COMPRESSION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration & Approval
    |--------------------------------------------------------------------------
    |
    | Configure self-registration for affiliates. The owner model determines
    | the polymorphic relationship owner. Approval mode controls how new
    | affiliate registrations are handled.
    |
    */

    'registration' => [
        'enabled' => env('AFFILIATES_REGISTRATION_ENABLED', true),
        'owner_model' => env('AFFILIATES_REGISTRATION_OWNER_MODEL', 'App\\Models\\User'),
        'approval_mode' => env('AFFILIATES_REGISTRATION_APPROVAL_MODE', 'admin'), // auto | open | admin
        'default_status' => env('AFFILIATES_REGISTRATION_DEFAULT_STATUS', 'pending'), // draft | pending | active
        'default_commission_type' => env('AFFILIATES_REGISTRATION_COMMISSION_TYPE', 'percentage'),
        'default_commission_rate' => env('AFFILIATES_REGISTRATION_COMMISSION_RATE', 1000), // 10% in basis points
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Options
    |--------------------------------------------------------------------------
    |
    | Respect the monorepo helper for JSON column type (json/jsonb).
    |
    */
];
