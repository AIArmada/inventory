---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions.

## Installation Issues

### Class not found errors

**Problem:** `Class 'AIArmada\Inventory\Models\InventoryLocation' not found`

**Solution:** Ensure the core inventory package is installed:

```bash
composer require aiarmada/inventory
php artisan migrate
```

### Plugin not appearing

**Problem:** Resources don't show in admin panel.

**Solution:** Register the plugin in your panel provider:

```php
use AIArmada\FilamentInventory\FilamentInventoryPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentInventoryPlugin::make(),
        ]);
}
```

### Missing routes

**Problem:** 404 errors when navigating to inventory resources.

**Solution:** Clear route cache:

```bash
php artisan route:clear
php artisan filament:clear-cached-components
```

## Configuration Issues

### Resources not visible

**Problem:** Some resources don't appear in navigation.

**Solution:** Check feature toggles in config:

```php
// config/filament-inventory.php
'features' => [
    'locations_resource' => true,  // Must be true
    'levels_resource' => true,
    'movements_resource' => true,
    'allocations_resource' => true,
    'batches_resource' => false,   // Disabled by default
    'serials_resource' => false,   // Disabled by default
],
```

### Widgets not showing

**Problem:** Dashboard widgets don't appear.

**Solution:** 

1. Check widget feature toggles:
```php
'features' => [
    'stats_widget' => true,
    'kpi_widget' => true,
    // etc.
],
```

2. Register widgets with your dashboard:
```php
use AIArmada\FilamentInventory\Widgets\InventoryStatsWidget;

class Dashboard extends BaseDashboard
{
    protected function getWidgets(): array
    {
        return [
            InventoryStatsWidget::class,
        ];
    }
}
```

### Cache not refreshing

**Problem:** Stats show stale data.

**Solution:** Clear the cache:

```php
use AIArmada\FilamentInventory\Services\InventoryStatsAggregator;

app(InventoryStatsAggregator::class)->clearCache();
```

Or via Artisan:

```bash
php artisan cache:clear
```

## Multitenancy Issues

### Cross-tenant data visible

**Problem:** Users see data from other tenants.

**Solution:** Ensure owner scoping is configured:

1. Core package must have owner mode enabled
2. Models must use `HasOwner` trait
3. Resources must override `getEloquentQuery()` if custom queries needed

### Empty dropdowns

**Problem:** Location/product selects show no options.

**Solution:** Ensure owner binding is set in request context:

```php
// In middleware or service provider
OwnerResolver::setOwner($currentTeam);
```

## Performance Issues

### Slow widget loading

**Problem:** Dashboard takes long to load.

**Solution:** 

1. Increase cache TTL:
```php
'cache' => [
    'stats_ttl' => 300, // 5 minutes
],
```

2. Disable unused widgets:
```php
'features' => [
    'movement_trends_chart' => false,
    'abc_analysis_chart' => false,
],
```

### Slow table loading

**Problem:** Resource tables load slowly.

**Solution:**

1. Check database indexes on frequently filtered columns
2. Reduce default items per page
3. Disable real-time polling:
```php
'tables' => [
    'poll' => false,
],
```

## Action Errors

### Insufficient stock

**Problem:** "Insufficient available quantity" when shipping/transferring.

**Solution:** Check actual available quantity:

```php
$level = InventoryStockLevel::query()
    ->where('location_id', $locationId)
    ->where('product_type', Product::class)
    ->where('product_id', $productId)
    ->first();

dump([
    'on_hand' => $level->quantity_on_hand,
    'reserved' => $level->quantity_reserved,
    'available' => $level->quantity_available,
]);
```

### Movement creation fails

**Problem:** Error creating inventory movement.

**Solution:** Ensure all required fields are provided:

- `location_id`
- `product_type` (full class name)
- `product_id`
- `movement_type` (receipt, shipment, adjustment, transfer_in, transfer_out)
- `quantity`

## Debug Mode

Enable debug logging for inventory operations:

```php
// config/logging.php
'channels' => [
    'inventory' => [
        'driver' => 'daily',
        'path' => storage_path('logs/inventory.log'),
        'level' => 'debug',
    ],
],
```

```php
// In your code
Log::channel('inventory')->debug('Stock received', $data);
```

## Getting Help

1. Check the [core inventory package documentation](../inventory/docs/)
2. Review the [source code](https://github.com/aiarmada/commerce/packages/filament-inventory)
3. Open an issue on GitHub with:
   - PHP version
   - Laravel version
   - Filament version
   - Full error message and stack trace
   - Steps to reproduce
