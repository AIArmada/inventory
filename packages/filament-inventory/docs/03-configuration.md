---
title: Configuration
---

# Configuration

The package configuration is published to `config/filament-inventory.php`.

## Full Configuration Reference

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'Inventory',

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        // Days before expiry to show in "Expiring Batches" widget
        'expiry_warning_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        // Costing method for valuation: fifo, lifo, average, specific
        'costing_method' => env('FILAMENT_INVENTORY_COSTING_METHOD', 'fifo'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        // Widgets
        'stats_widget' => env('FILAMENT_INVENTORY_STATS_WIDGET', true),
        'low_stock_widget' => env('FILAMENT_INVENTORY_LOW_STOCK_WIDGET', true),
        'expiring_batches_widget' => env('FILAMENT_INVENTORY_EXPIRING_BATCHES_WIDGET', true),
        'reorder_suggestions_widget' => env('FILAMENT_INVENTORY_REORDER_SUGGESTIONS_WIDGET', true),
        'backorders_widget' => env('FILAMENT_INVENTORY_BACKORDERS_WIDGET', true),
        'valuation_widget' => env('FILAMENT_INVENTORY_VALUATION_WIDGET', true),
        'kpi_widget' => env('FILAMENT_INVENTORY_KPI_WIDGET', true),
        'movement_trends_chart' => env('FILAMENT_INVENTORY_MOVEMENT_TRENDS_CHART', true),
        'abc_analysis_chart' => env('FILAMENT_INVENTORY_ABC_ANALYSIS_CHART', true),

        // Resources
        'batch_resource' => env('FILAMENT_INVENTORY_BATCH_RESOURCE', true),
        'serial_resource' => env('FILAMENT_INVENTORY_SERIAL_RESOURCE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */
    'resources' => [
        'navigation_sort' => [
            'locations' => 10,
            'levels' => 20,
            'movements' => 30,
            'allocations' => 40,
            'batches' => 50,
            'serials' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        // TTL in seconds for dashboard stats caching
        'stats_ttl' => env('FILAMENT_INVENTORY_STATS_CACHE_TTL', 60),
    ],
];
```

## Configuration Options

### Navigation Group

```php
'navigation_group' => 'Inventory',
```

Groups all inventory resources under this navigation label. Set to `null` to place resources at the top level.

### Expiry Warning Days

```php
'tables' => [
    'expiry_warning_days' => 30,
],
```

Batches expiring within this number of days will appear in the "Expiring Batches" widget.

### Costing Method

```php
'defaults' => [
    'costing_method' => 'fifo',
],
```

Options:
- `fifo` — First In, First Out
- `lifo` — Last In, First Out
- `average` — Weighted Average Cost
- `specific` — Specific Identification

### Feature Toggles

Each widget and optional resource can be enabled/disabled:

```php
'features' => [
    'stats_widget' => true,
    'batch_resource' => true,
    'serial_resource' => true,
    // ...
],
```

### Navigation Sort Order

```php
'resources' => [
    'navigation_sort' => [
        'locations' => 10,
        'levels' => 20,
        // ...
    ],
],
```

Lower numbers appear first in navigation.

### Stats Cache TTL

```php
'cache' => [
    'stats_ttl' => 60,
],
```

Dashboard stats are cached for this many seconds. Set to `0` to disable caching.

## Customizing Resources

To extend or customize a resource, you can create your own that extends the base:

```php
<?php

namespace App\Filament\Resources;

use AIArmada\FilamentInventory\Resources\InventoryLocationResource as BaseResource;

class InventoryLocationResource extends BaseResource
{
    public static function getNavigationGroup(): ?string
    {
        return 'Warehouse';
    }
}
```

Then register your custom resource in the plugin:

```php
FilamentInventoryPlugin::make()
    ->resources([
        CustomInventoryLocationResource::class,
    ]);
```
