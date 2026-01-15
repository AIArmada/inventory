---
title: Configuration
---

# Configuration

Complete configuration reference for the Filament JNT package.

---

## Configuration File

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-jnt-config
```

---

## Full Configuration

```php
// config/filament-jnt.php

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'Shipping',
    'navigation_badge_color' => 'primary',

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'polling_interval' => '30s',

    'tables' => [
        'datetime_format' => 'Y-m-d H:i:s',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'orders' => true,
        'tracking_events' => true,
        'webhook_logs' => true,
        'widgets' => true,
        'show_raw_payloads' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */
    'resources' => [
        'navigation_sort' => [
            'orders' => 10,
            'tracking_events' => 20,
            'webhook_logs' => 30,
        ],
    ],
];
```

---

## Options Reference

### Navigation

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `navigation_group` | `string` | `'Shipping'` | Navigation group label |
| `navigation_badge_color` | `string` | `'primary'` | Badge color for counts |

**Example**:
```php
'navigation_group' => 'Logistics',
'navigation_badge_color' => 'success',
```

### Tables

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `polling_interval` | `string` | `'30s'` | Auto-refresh interval |
| `tables.datetime_format` | `string` | `'Y-m-d H:i:s'` | Date/time format |

**Example**:
```php
'polling_interval' => '60s',  // Less frequent updates
'tables' => [
    'datetime_format' => 'd M Y, H:i',  // "25 Dec 2024, 14:30"
],
```

### Features

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `features.orders` | `bool` | `true` | Enable orders resource |
| `features.tracking_events` | `bool` | `true` | Enable tracking events resource |
| `features.webhook_logs` | `bool` | `true` | Enable webhook logs resource |
| `features.widgets` | `bool` | `true` | Enable dashboard widgets |
| `features.show_raw_payloads` | `bool` | `false` | Show raw JSON payloads |

**Example**:
```php
'features' => [
    'orders' => true,
    'tracking_events' => true,
    'webhook_logs' => false,  // Hide from panel
    'widgets' => true,
    'show_raw_payloads' => true,  // For debugging
],
```

### Resources

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `resources.navigation_sort.orders` | `int` | `10` | Order resource sort position |
| `resources.navigation_sort.tracking_events` | `int` | `20` | Tracking events sort position |
| `resources.navigation_sort.webhook_logs` | `int` | `30` | Webhook logs sort position |

**Example**:
```php
'resources' => [
    'navigation_sort' => [
        'orders' => 1,          // First in group
        'tracking_events' => 2,
        'webhook_logs' => 99,   // Last in group
    ],
],
```

---

## Plugin Configuration

Configure features via the plugin fluent API:

```php
use AIArmada\FilamentJnt\FilamentJntPlugin;

FilamentJntPlugin::make()
    ->orders(true)
    ->trackingEvents(true)
    ->webhookLogs(false)
    ->widgets(true);
```

Plugin settings override config file settings when explicitly set.

### Priority Order

1. Plugin fluent methods (highest)
2. Config file values
3. Default values (lowest)

---

## Multi-Tenant Configuration

For multi-tenant applications, ensure the core JNT package is configured:

```env
# .env
JNT_OWNER_ENABLED=true
JNT_OWNER_INCLUDE_GLOBAL=false
JNT_OWNER_AUTO_ASSIGN=true
```

The Filament resources automatically:
- Filter queries by current tenant
- Display badge counts per tenant
- Cache widget stats per tenant

---

## Polling Configuration

Control how often tables auto-refresh:

```php
// Disable polling
'polling_interval' => null,

// Fast updates (high traffic)
'polling_interval' => '10s',

// Slow updates (low traffic)
'polling_interval' => '120s',
```

---

## Date Formats

Customize date/time display:

```php
'tables' => [
    // Full datetime
    'datetime_format' => 'Y-m-d H:i:s',
    
    // Human-friendly
    'datetime_format' => 'd M Y, H:i',
    
    // Date only
    'datetime_format' => 'Y-m-d',
    
    // 12-hour format
    'datetime_format' => 'd/m/Y h:i A',
],
```

---

## Badge Colors

Available colors for navigation badges:

- `primary` (default)
- `success`
- `warning`
- `danger`
- `info`
- `gray`

```php
'navigation_badge_color' => 'info',
```

---

## Environment-Based Configuration

Use environment variables for dynamic configuration:

```php
// config/filament-jnt.php
return [
    'features' => [
        'webhook_logs' => env('FILAMENT_JNT_SHOW_WEBHOOKS', true),
        'show_raw_payloads' => env('FILAMENT_JNT_DEBUG', false),
    ],
];
```

```env
# .env
FILAMENT_JNT_SHOW_WEBHOOKS=false  # Hide in production
FILAMENT_JNT_DEBUG=true           # Enable in development
```

---

## Configuration Caching

When deploying, cache configuration:

```bash
php artisan config:cache
```

Clear cache after changes:

```bash
php artisan config:clear
```
