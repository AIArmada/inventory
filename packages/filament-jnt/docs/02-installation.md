---
title: Installation
---

# Installation

This guide covers installing and setting up the Filament JNT package.

---

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament v5
- `aiarmada/jnt` package installed and configured

---

## Install Package

Install via Composer:

```bash
composer require aiarmada/filament-jnt
```

The package auto-discovers the service provider.

---

## Publish Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=filament-jnt-config
```

This creates `config/filament-jnt.php`.

---

## Register Plugin

Add the plugin to your Filament panel:

```php
// app/Providers/Filament/AdminPanelProvider.php

use AIArmada\FilamentJnt\FilamentJntPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->plugins([
            FilamentJntPlugin::make(),
        ]);
}
```

---

## Feature Selection

Enable or disable specific features using the fluent API:

```php
FilamentJntPlugin::make()
    ->orders()           // Enable orders resource (default: true)
    ->trackingEvents()   // Enable tracking events resource (default: true)
    ->webhookLogs()      // Enable webhook logs resource (default: true)
    ->widgets(),         // Enable dashboard widgets (default: true)
```

Disable specific features:

```php
FilamentJntPlugin::make()
    ->orders(true)
    ->trackingEvents(true)
    ->webhookLogs(false)    // Disable webhook logs
    ->widgets(false),       // Disable widgets
```

Or configure via config file:

```php
// config/filament-jnt.php
'features' => [
    'orders' => true,
    'tracking_events' => true,
    'webhook_logs' => false,
    'widgets' => true,
],
```

---

## Prerequisites

Ensure the core JNT package is properly configured:

1. **Run migrations**:
   ```bash
   php artisan migrate
   ```

2. **Configure environment**:
   ```env
   JNT_ENVIRONMENT=testing
   JNT_API_ACCOUNT=your_api_account
   JNT_PRIVATE_KEY=your_private_key
   JNT_CUSTOMER_CODE=your_customer_code
   JNT_PASSWORD=your_password
   ```

3. **Verify configuration**:
   ```bash
   php artisan jnt:config-check
   ```

See the [JNT package installation](../../jnt/docs/02-installation.md) for detailed setup.

---

## Multi-Panel Setup

Register the plugin in multiple panels:

```php
// AdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->plugins([
            FilamentJntPlugin::make()
                ->orders()
                ->trackingEvents()
                ->webhookLogs()
                ->widgets(),
        ]);
}

// OperationsPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('operations')
        ->plugins([
            FilamentJntPlugin::make()
                ->orders()
                ->trackingEvents()
                ->webhookLogs(false)  // Hide from operations
                ->widgets(),
        ]);
}
```

---

## Tenancy Setup

The package integrates with Filament's tenancy system:

```php
// Panel with tenancy
public function panel(Panel $panel): Panel
{
    return $panel
        ->tenant(Team::class)
        ->plugins([
            FilamentJntPlugin::make(),
        ]);
}
```

Enable owner scoping in JNT config:

```env
JNT_OWNER_ENABLED=true
JNT_OWNER_INCLUDE_GLOBAL=false
```

Resources are automatically filtered by the current tenant.

---

## Verification

After installation:

1. **Visit your panel**: `/admin` (or your panel path)
2. **Check navigation**: "Shipping" group should appear
3. **Verify resources**: Orders, Tracking Events, Webhook Logs
4. **Check widget**: Stats widget on dashboard

---

## Troubleshooting

### Resources Not Appearing

1. Verify plugin is registered:
   ```php
   FilamentJntPlugin::make()
   ```

2. Check feature toggles in config:
   ```php
   'features' => ['orders' => true, ...]
   ```

3. Clear caches:
   ```bash
   php artisan filament:clear-cached-components
   php artisan config:clear
   ```

### Widget Not Showing

1. Enable in plugin:
   ```php
   FilamentJntPlugin::make()->widgets()
   ```

2. Or in config:
   ```php
   'features' => ['widgets' => true]
   ```

3. Ensure you're authenticated

### Navigation Group Missing

Check config for group name:

```php
// config/filament-jnt.php
'navigation_group' => 'Shipping',
```

---

## Next Steps

- [Configuration](03-configuration.md) - Customize behavior
- [Usage](04-usage.md) - Using the resources and actions
