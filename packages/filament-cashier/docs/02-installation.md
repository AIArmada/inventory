---
title: Installation
---

# Installation

This guide walks through installing and configuring Filament Cashier for multi-gateway billing.

## Step 1: Install the Package

```bash
composer require aiarmada/filament-cashier
```

This automatically installs `aiarmada/cashier` as a dependency.

## Step 2: Install Gateway Packages

Install at least one gateway package:

**For Stripe:**
```bash
composer require laravel/cashier
```

**For CHIP:**
```bash
composer require aiarmada/cashier-chip
```

**For both:**
```bash
composer require laravel/cashier aiarmada/cashier-chip
```

## Step 3: Publish Configuration

```bash
php artisan vendor:publish --tag=filament-cashier-config
```

This creates `config/filament-cashier.php`.

## Step 4: Publish Translations (Optional)

```bash
php artisan vendor:publish --tag=filament-cashier-translations
```

Creates translation files in `lang/vendor/filament-cashier/`.

## Step 5: Run Gateway Migrations

Each gateway has its own migrations:

**For Stripe:**
```bash
php artisan vendor:publish --tag=cashier-migrations
php artisan migrate
```

**For CHIP:**
```bash
php artisan vendor:publish --tag=cashier-chip-migrations
php artisan migrate
```

## Step 6: Configure Model

Add the `Billable` trait to your User model:

```php
<?php

namespace App\Models;

use AIArmada\Cashier\Billable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Billable;
    
    // ...
}
```

## Step 7: Register the Plugin

Add the plugin to your Filament panel:

```php
<?php

namespace App\Providers\Filament;

use AIArmada\FilamentCashier\FilamentCashierPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugins([
                FilamentCashierPlugin::make(),
            ]);
    }
}
```

## Environment Configuration

### Stripe Credentials

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### CHIP Credentials

```env
CHIP_BRAND_ID=your_brand_id
CHIP_API_KEY=your_api_key
CHIP_WEBHOOK_KEY=your_webhook_key
```

### Default Gateway

```env
CASHIER_GATEWAY=stripe
CASHIER_CURRENCY=USD
CASHIER_CURRENCY_LOCALE=en_US
```

## Plugin Options

Configure the plugin with available options:

```php
FilamentCashierPlugin::make()
    // Navigation
    ->navigationGroup('Billing')
    ->navigationSort(50)
    
    // Features
    ->dashboard()           // Enable billing dashboard (default: true)
    ->subscriptions()       // Enable subscriptions resource (default: true)
    ->invoices()            // Enable invoices resource (default: true)
    ->gatewayManagement()   // Enable gateway management page (default: false)
    ->customerPortalMode()  // Enable customer portal mode (default: false)
```

### Disabling Features

```php
FilamentCashierPlugin::make()
    ->dashboard(false)      // Disable dashboard
    ->invoices(false)       // Disable invoices
```

## Customer Portal Setup

For a customer-facing billing portal, use the `BillingPanelProvider`:

```php
// config/filament-cashier.php
'billing_portal' => [
    'enabled' => true,
    'panel_id' => 'billing',
    'path' => 'billing',
    'brand_name' => 'My App Billing',
    'primary_color' => '#6366f1',
    'auth_guard' => 'web',
    'features' => [
        'subscriptions' => true,
        'payment_methods' => true,
        'invoices' => true,
    ],
],
```

Then register the panel provider:

```php
// config/app.php or bootstrap/providers.php
'providers' => [
    // ...
    AIArmada\FilamentCashier\CustomerPortal\BillingPanelProvider::class,
],
```

## Verify Installation

After installation, verify everything works:

1. Navigate to `/admin/billing-dashboard` - you should see the dashboard
2. Navigate to `/admin/subscriptions` - you should see the subscriptions list
3. Navigate to `/admin/invoices` - you should see the invoices list

If no gateways are installed, you'll see the Gateway Setup page with installation instructions.

## Troubleshooting

### "No gateways detected"

Install at least one gateway package:
```bash
composer require laravel/cashier
# or
composer require aiarmada/cashier-chip
```

### Dashboard shows no data

1. Ensure your models use the `Billable` trait
2. Verify gateway credentials are configured
3. Check that migrations have been run

### Missing translations

Publish the translation files:
```bash
php artisan vendor:publish --tag=filament-cashier-translations
```

### Permissions errors

Ensure your policies allow access. See the [Configuration](03-configuration.md) guide for policy customization.
