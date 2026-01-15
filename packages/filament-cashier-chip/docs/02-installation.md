---
title: Installation
---

# Installation

## Requirements

- PHP 8.2+
- Laravel 11+
- Filament 5.x
- aiarmada/cashier-chip package (installed automatically as dependency)

## Installing the Package

```bash
composer require aiarmada/filament-cashier-chip
```

This will also install the `aiarmada/cashier-chip` dependency if not already present.

## Register the Plugin

Add the plugin to your Filament panel:

```php
use AIArmada\FilamentCashierChip\FilamentCashierChipPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                FilamentCashierChipPlugin::make(),
            ]);
    }
}
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=filament-cashier-chip-config
```

This creates `config/filament-cashier-chip.php`.

## Publish Views (Optional)

To customize the Blade views:

```bash
php artisan vendor:publish --tag=filament-cashier-chip-views
```

## Publish Translations (Optional)

To customize translations:

```bash
php artisan vendor:publish --tag=filament-cashier-chip-translations
```

## Enabling the Billing Portal

If you want customers to manage their own billing, register the `BillingPanelProvider`:

```php
// config/app.php
'providers' => [
    // ...
    AIArmada\FilamentCashierChip\BillingPanelProvider::class,
],
```

Or in a service provider:

```php
// app/Providers/AppServiceProvider.php
use AIArmada\FilamentCashierChip\BillingPanelProvider;

public function register(): void
{
    $this->app->register(BillingPanelProvider::class);
}
```

The billing portal will be available at `/billing` (configurable).

## Plugin Configuration

Configure the plugin inline:

```php
FilamentCashierChipPlugin::make()
    ->subscriptions(true)     // Enable subscription resource
    ->customers(true)         // Enable customer resource
    ->invoices(true)          // Enable invoice resource
    ->dashboardWidgets(true)  // Enable dashboard widgets
    ->billingDashboard(true)  // Enable billing dashboard page
    ->billingPortal(true)     // Enable billing portal pages
```

## Selective Feature Loading

Disable features you don't need:

```php
FilamentCashierChipPlugin::make()
    ->subscriptions(true)
    ->customers(false)        // Disable customer resource
    ->invoices(false)         // Disable invoice resource
    ->dashboardWidgets(false) // Disable dashboard widgets
```

## Multi-Panel Setup

For different panels with different features:

```php
// Admin panel - full access
FilamentCashierChipPlugin::make()
    ->subscriptions(true)
    ->customers(true)
    ->invoices(true)
    ->dashboardWidgets(true);

// Customer panel - limited access
FilamentCashierChipPlugin::make()
    ->subscriptions(false)
    ->customers(false)
    ->invoices(true)
    ->billingPortal(true);
```

## Verify Installation

1. Clear caches:
   ```bash
   php artisan optimize:clear
   ```

2. Visit your Filament admin panel

3. Check for "Billing" navigation group with:
   - Subscriptions
   - Customers
   - Invoices

## Next Steps

- [Configuration](03-configuration.md) – Customize settings
- [Resources](04-resources.md) – Learn about admin resources
- [Billing Portal](05-billing-portal.md) – Customer self-service setup
