---
title: Configuration
---

# Configuration

## Publish Configuration

```bash
php artisan vendor:publish --tag="filament-chip-config"
```

## Full Configuration Reference

```php
<?php

// config/filament-chip.php

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Customize where CHIP resources appear in the Filament navigation.
    |
    */
    'navigation' => [
        'group' => 'Payments',
        'sort' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | Table display settings.
    |
    */
    'tables' => [
        'poll_interval' => '30s',      // Auto-refresh interval
        'date_format' => 'd M Y H:i',  // Date display format
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific features of the plugin.
    |
    */
    'features' => [
        'analytics_dashboard' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Override default resource classes with custom implementations.
    | Set to null to disable a resource entirely.
    |
    */
    'resources' => [
        'purchase' => \AIArmada\FilamentChip\Resources\PurchaseResource::class,
        'client' => \AIArmada\FilamentChip\Resources\ClientResource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Default currency for display formatting.
    |
    */
    'currency' => 'MYR',
];
```

## Environment Variables

The plugin uses environment variables from the core `chip` package:

```env
# Environment: 'sandbox' or 'production'
CHIP_ENVIRONMENT=sandbox

# CHIP Collect API (Payments)
CHIP_BRAND_ID=your-brand-uuid
CHIP_COLLECT_API_KEY=your-collect-api-key

# CHIP Send API (Payouts)
CHIP_SEND_API_KEY=your-send-api-key
CHIP_SEND_API_SECRET=your-send-api-secret

# Webhook Verification
CHIP_COMPANY_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
CHIP_WEBHOOK_VERIFY_SIGNATURE=true
```

## Plugin Configuration Methods

Configure the plugin fluently in your panel provider:

```php
use AIArmada\FilamentChip\FilamentChipPlugin;

$panel->plugin(
    FilamentChipPlugin::make()
);
```

## Disabling Resources

Disable specific resources by setting them to `null` in config:

```php
// config/filament-chip.php
'resources' => [
    'purchase' => PurchaseResource::class,
    'client' => null,  // Disabled
],
```

## Custom Navigation Group

Change the navigation group for all CHIP resources:

```php
// config/filament-chip.php
'navigation' => [
    'group' => 'Finance',  // Custom group name
    'sort' => 10,          // Sort order within sidebar
],
```

## Table Polling

Configure auto-refresh interval for tables:

```php
'tables' => [
    'poll_interval' => '10s',  // Faster updates
],
```

Set to `null` to disable polling:

```php
'tables' => [
    'poll_interval' => null,  // No auto-refresh
],
```
