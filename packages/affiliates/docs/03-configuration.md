---
title: Configuration
---

# Configuration

The package is configured via `config/affiliates.php`. This document covers all available options organized by section.

## Database

```php
'database' => [
    'table_prefix' => env('AFFILIATES_TABLE_PREFIX', 'affiliate_'),
    'json_column_type' => env('AFFILIATES_JSON_COLUMN_TYPE', 'json'),
    'tables' => [
        'affiliates' => 'affiliate_affiliates',
        'attributions' => 'affiliate_attributions',
        'conversions' => 'affiliate_conversions',
        // ... 25+ tables
    ],
],
```

| Key | Description |
|-----|-------------|
| `table_prefix` | Prefix for all affiliate tables |
| `json_column_type` | Column type for JSON fields (`json` or `jsonb` for PostgreSQL) |
| `tables` | Override individual table names |

## Currency

```php
'currency' => [
    'default' => env('AFFILIATES_DEFAULT_CURRENCY', 'USD'),
    'percentage_scale' => env('AFFILIATES_PERCENTAGE_SCALE', 100),
],
```

| Key | Description |
|-----|-------------|
| `default` | Default ISO 4217 currency code |
| `percentage_scale` | Basis points per 1% (100 = 1%) |

## Features / Behavior

```php
'features' => [
    'cart_integration' => [
        'enabled' => env('AFFILIATES_FEATURE_CART_INTEGRATION', true),
    ],
    'voucher_integration' => [
        'enabled' => env('AFFILIATES_FEATURE_VOUCHER_INTEGRATION', true),
    ],
    'commission_tracking' => [
        'enabled' => env('AFFILIATES_FEATURE_COMMISSION_TRACKING', true),
    ],
],
```

`commission_tracking.enabled` is also used by the Filament plugin to hide payout and program surfaces when commission tracking is disabled.

## Multi-Tenancy (Owner Scoping)

```php
'owner' => [
    'enabled' => env('AFFILIATES_OWNER_ENABLED', false),
    'include_global' => env('AFFILIATES_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('AFFILIATES_OWNER_AUTO_ASSIGN', true),
],
```

| Key | Description |
|-----|-------------|
| `enabled` | Enable owner-based query scoping |
| `include_global` | Include records with `owner_id = null` in queries |
| `auto_assign_on_create` | Automatically set owner from context on new records |

## Cart Integration

```php
'cart' => [
    'metadata_key' => env('AFFILIATES_CART_METADATA_KEY', 'affiliate'),
    'register_manager_proxy' => env('AFFILIATES_CART_PROXY', true),
    'persist_metadata' => env('AFFILIATES_CART_PERSIST_METADATA', true),
    'customer_discounts_enabled' => env('AFFILIATES_CUSTOMER_DISCOUNTS_ENABLED', false),
],
```

| Key | Description |
|-----|-------------|
| `metadata_key` | Key for storing affiliate data in cart metadata |
| `register_manager_proxy` | Register fluent Cart facade helpers |
| `persist_metadata` | Store affiliate metadata on cart persistence |
| `customer_discounts_enabled` | Enable affiliate-based customer discounts |

## Cookie Tracking

```php
'cookies' => [
    'enabled' => env('AFFILIATES_COOKIES_ENABLED', true),
    'name' => env('AFFILIATES_COOKIE_NAME', 'affiliate_session'),
    'ttl_minutes' => env('AFFILIATES_COOKIE_TTL_MINUTES', 43200), // 30 days
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
```

| Key | Description |
|-----|-------------|
| `enabled` | Enable cookie-based tracking |
| `name` | Cookie name for affiliate session |
| `ttl_minutes` | Cookie lifetime in minutes |
| `query_parameters` | URL parameters to check for affiliate codes |
| `auto_register_middleware` | Auto-add middleware to `web` group |
| `respect_dnt` | Honor Do Not Track browser headers |
| `require_consent` | Require explicit consent before tracking |
| `consent_cookie` | Cookie name for consent flag |

## Voucher Integration

```php
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
```

| Key | Description |
|-----|-------------|
| `attach_on_apply` | Auto-attach affiliate when voucher applied |
| `metadata_keys` | Dot-notation paths to check for affiliate codes |
| `match_default_voucher_code` | Match voucher code to affiliate's default voucher |

## Commission Settings

```php
'commissions' => [
    'auto_approve' => env('AFFILIATES_AUTO_APPROVE', false),
    'default_status' => 'pending',
    'default_rate' => env('AFFILIATES_DEFAULT_COMMISSION_RATE', 1000), // 10%
    'minimum_minor' => env('AFFILIATES_MINIMUM_COMMISSION_MINOR', 0),
    'maximum_minor' => env('AFFILIATES_MAXIMUM_COMMISSION_MINOR'),
],
```

| Key | Description |
|-----|-------------|
| `auto_approve` | Automatically approve new conversions |
| `default_status` | Default status for new conversions |
| `default_rate` | Default commission rate in basis points |
| `minimum_minor` | Minimum commission amount (minor units) |
| `maximum_minor` | Maximum commission amount (minor units) |

## Payout Settings

```php
'payouts' => [
    'currency' => env('AFFILIATES_PAYOUT_CURRENCY', 'USD'),
    'reference_prefix' => env('AFFILIATES_PAYOUT_REF_PREFIX', 'PO-'),
    'minimum_amount' => env('AFFILIATES_PAYOUT_MINIMUM_AMOUNT', 5000), // $50.00
    'maturity_days' => env('AFFILIATES_PAYOUT_MATURITY_DAYS', 30),
    'multi_level' => [
        'enabled' => env('AFFILIATES_MULTI_LEVEL_ENABLED', false),
        'levels' => [0.1, 0.05], // 10%, 5% of commission to uplines
    ],
    'paypal' => [
        'client_id' => env('AFFILIATES_PAYPAL_CLIENT_ID'),
        'client_secret' => env('AFFILIATES_PAYPAL_CLIENT_SECRET'),
        'sandbox' => env('AFFILIATES_PAYPAL_SANDBOX', true),
    ],
    'stripe' => [
        'secret_key' => env('AFFILIATES_STRIPE_SECRET_KEY'),
    ],
],
```

## Tracking Settings

```php
'tracking' => [
    'attribution_ttl_days' => env('AFFILIATES_ATTRIBUTION_TTL_DAYS', 30),
    'max_attributions_per_identifier' => env('AFFILIATES_ATTRIBUTION_MAX', 5),
    'block_self_referral' => env('AFFILIATES_BLOCK_SELF_REFERRAL', false),
    'ip_rate_limit' => [
        'enabled' => env('AFFILIATES_IP_RATE_LIMIT_ENABLED', false),
        'max' => env('AFFILIATES_IP_RATE_LIMIT_MAX', 20),
        'decay_minutes' => env('AFFILIATES_IP_RATE_LIMIT_DECAY', 30),
    ],
    'attribution_model' => env('AFFILIATES_ATTRIBUTION_MODEL', 'last_touch'),
    'fingerprint' => [
        'enabled' => env('AFFILIATES_FINGERPRINT_ENABLED', false),
        'block_duplicates' => env('AFFILIATES_FINGERPRINT_BLOCK_DUPLICATES', false),
        'threshold' => env('AFFILIATES_FINGERPRINT_THRESHOLD', 5),
    ],
],
```

| Key | Description |
|-----|-------------|
| `attribution_ttl_days` | How long attributions remain valid |
| `attribution_model` | `last_touch`, `first_touch`, or `linear` |
| `block_self_referral` | Prevent affiliates from crediting themselves |

## Fraud Detection

```php
'fraud' => [
    'enabled' => env('AFFILIATES_FRAUD_ENABLED', true),
    'blocking_threshold' => env('AFFILIATES_FRAUD_BLOCK_THRESHOLD', 100),
    'velocity' => [
        'enabled' => env('AFFILIATES_FRAUD_VELOCITY_ENABLED', true),
        'max_clicks_per_hour' => env('AFFILIATES_FRAUD_MAX_CLICKS_HOUR', 100),
        'max_conversions_per_day' => env('AFFILIATES_FRAUD_MAX_CONVERSIONS_DAY', 50),
    ],
    'anomaly' => [
        'geo' => ['enabled' => env('AFFILIATES_FRAUD_GEO_ENABLED', false)],
        'conversion_time' => ['min_seconds' => env('AFFILIATES_FRAUD_MIN_CONVERSION_SECONDS', 5)],
    ],
],
```

## Network/MLM

```php
'network' => [
    'enabled' => env('AFFILIATES_NETWORK_ENABLED', false),
    'max_depth' => env('AFFILIATES_NETWORK_MAX_DEPTH', 10),
],
```

## Registration & Approval

```php
'registration' => [
    'enabled' => env('AFFILIATES_REGISTRATION_ENABLED', true),
    'approval_mode' => env('AFFILIATES_REGISTRATION_APPROVAL_MODE', 'admin'),
    'default_commission_type' => env('AFFILIATES_REGISTRATION_COMMISSION_TYPE', 'percentage'),
    'default_commission_rate' => env('AFFILIATES_REGISTRATION_COMMISSION_RATE', 1000),
],
```

| Approval Mode | Behavior |
|---------------|----------|
| `auto` | Immediately activate new affiliates |
| `open` | Create as pending, auto-approve on first conversion |
| `admin` | Require manual admin approval |

## Events & Webhooks

```php
'events' => [
    'dispatch_attributed' => env('AFFILIATES_EVENT_ATTRIBUTED', true),
    'dispatch_conversion' => env('AFFILIATES_EVENT_CONVERSION', true),
    'dispatch_webhooks' => env('AFFILIATES_EVENT_WEBHOOKS', false),
],

'webhooks' => [
    'signature_secret' => env('AFFILIATES_WEBHOOK_SIGNATURE_SECRET'),
    'endpoints' => [
        'attribution' => [],
        'conversion' => [],
        'payout' => [],
    ],
],
```

## API

```php
'api' => [
    'enabled' => env('AFFILIATES_API_ENABLED', false),
    'prefix' => env('AFFILIATES_API_PREFIX', 'api/affiliates'),
    'middleware' => ['api', 'throttle:60,1'],
    'auth' => env('AFFILIATES_API_AUTH', 'token'),
    'token' => env('AFFILIATES_API_TOKEN'),
],
```

### Link Payload Notes

`POST /api/affiliates/{code}/links` supports subject-aware link metadata:

```json
{
    "url": "https://example.com/products/sku-1001",
    "ttl": 3600,
    "params": {"utm_campaign": "spring-launch"},
    "subject_type": "product",
    "subject_identifier": "SKU-1001",
    "subject_instance": "web",
    "subject_title_snapshot": "Pro Plan",
    "subject_metadata": {"category": "subscriptions"}
}
```
