---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament 5.0+
- `aiarmada/inventory` package

## Install via Composer

```bash
composer require aiarmada/filament-inventory
```

The package will auto-register its service provider via Laravel's package discovery.

## Publish Configuration

```bash
php artisan vendor:publish --tag=filament-inventory-config
```

This creates `config/filament-inventory.php` for customization.

## Register the Plugin

Add the plugin to your Filament panel provider:

```php
<?php

namespace App\Providers\Filament;

use AIArmada\FilamentInventory\FilamentInventoryPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->plugins([
                FilamentInventoryPlugin::make(),
            ]);
    }
}
```

## Verify Installation

After installation, you should see:

1. **Navigation Group**: "Inventory" with resources for Locations, Stock Levels, Movements, Allocations
2. **Dashboard Widgets**: Stats overview, low inventory alerts (if widgets are enabled)
3. **Optional Resources**: Batches and Serial Numbers (if enabled in config)

## Core Package Dependency

Ensure the core `aiarmada/inventory` package is properly configured:

```bash
php artisan migrate
```

This runs the inventory migrations for locations, levels, movements, allocations, batches, and serials.

## Environment Variables

The package supports these optional environment variables:

```env
# Widget toggles
FILAMENT_INVENTORY_STATS_WIDGET=true
FILAMENT_INVENTORY_LOW_STOCK_WIDGET=true
FILAMENT_INVENTORY_EXPIRING_BATCHES_WIDGET=true
FILAMENT_INVENTORY_REORDER_SUGGESTIONS_WIDGET=true
FILAMENT_INVENTORY_BACKORDERS_WIDGET=true
FILAMENT_INVENTORY_VALUATION_WIDGET=true
FILAMENT_INVENTORY_KPI_WIDGET=true
FILAMENT_INVENTORY_MOVEMENT_TRENDS_CHART=true
FILAMENT_INVENTORY_ABC_ANALYSIS_CHART=true

# Resource toggles
FILAMENT_INVENTORY_BATCH_RESOURCE=true
FILAMENT_INVENTORY_SERIAL_RESOURCE=true

# Costing method for valuation widget
FILAMENT_INVENTORY_COSTING_METHOD=fifo

# Cache TTL for stats (seconds)
FILAMENT_INVENTORY_STATS_CACHE_TTL=60
```
