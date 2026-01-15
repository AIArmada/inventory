---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament v5
- aiarmada/vouchers (automatically installed as dependency)

## Install via Composer

```bash
composer require aiarmada/filament-vouchers
```

This will also install the core `aiarmada/vouchers` package.

## Register the Plugin

Add the plugin to your Filament panel provider:

```php
// app/Providers/Filament/AdminPanelProvider.php
use AIArmada\FilamentVouchers\FilamentVouchersPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('admin')
        ->plugins([
            FilamentVouchersPlugin::make(),
        ]);
}
```

## Run Migrations

The core vouchers package provides database migrations:

```bash
php artisan migrate
```

## Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=filament-vouchers-config
```

This creates `config/filament-vouchers.php` for customization.

## Publish Views (Optional)

```bash
php artisan vendor:publish --tag=filament-vouchers-views
```

## Verify Installation

After installation:

1. Navigate to your Filament admin panel
2. Look for the "Vouchers" resource in the sidebar
3. Create your first voucher to test the setup

## Cart Integration (Optional)

For seamless cart integration, install the Filament Cart package:

```bash
composer require aiarmada/filament-cart
```

When both packages are installed, additional cart-related features are automatically enabled:

- Apply voucher actions on cart pages
- Voucher suggestions widget
- Applied vouchers badges
- Quick apply widget
