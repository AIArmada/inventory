# Installation

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament 5+
- aiarmada/cart package

## Install via Composer

```bash
composer require aiarmada/filament-cart
```

## Register the Plugin

Add `FilamentCartPlugin` to your Filament panel provider:

```php
<?php

namespace App\Providers\Filament;

use AIArmada\FilamentCart\FilamentCartPlugin;
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
                FilamentCartPlugin::make(),
            ]);
    }
}
```

## Publish Configuration

Optionally publish the configuration file:

```bash
php artisan vendor:publish --tag="filament-cart-config"
```

This creates `config/filament-cart.php` where you can customize:

- Navigation group name
- Polling interval for real-time updates
- Global conditions behavior
- Queue synchronization settings

## Database Requirements

The plugin uses the `aiarmada/cart` package migrations. Ensure you have run:

```bash
php artisan migrate
```

Required tables:
- `carts` — Normalized cart snapshots
- `cart_items` — Cart line items
- `cart_conditions` — Applied conditions
- `conditions` — Reusable condition templates

## Verify Installation

After installation, navigate to your Filament admin panel. You should see the **E-Commerce** navigation group with:

- Carts
- Cart Items
- Cart Conditions
- Conditions

The `CartStatsWidget` will appear on your dashboard showing cart metrics.
