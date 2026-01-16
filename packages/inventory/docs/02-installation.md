---
title: Installation
---

# Installation

## Requirements

- PHP 8.4 or higher
- Laravel 12.x
- `aiarmada/commerce-support` package

## Install via Composer

```bash
composer require aiarmada/inventory
```

The package will auto-register its service provider.

## Publish Configuration

```bash
php artisan vendor:publish --tag=inventory-config
```

This publishes `config/inventory.php` with all configuration options.

## Run Migrations

```bash
php artisan migrate
```

This creates the following tables (with configurable prefix):

| Table | Purpose |
|-------|---------|
| `inv_locations` | Warehouse/bin locations |
| `inv_levels` | Stock levels per SKU per location |
| `inv_movements` | Movement audit trail |
| `inv_allocations` | Cart/order reservations |
| `inv_batches` | Batch/lot tracking |
| `inv_serials` | Serial number tracking |
| `inv_serial_history` | Serial number audit trail |
| `inv_cost_layers` | FIFO cost layers |
| `inv_standard_costs` | Standard cost records |
| `inv_valuation_snapshots` | Period-end valuations |
| `inv_backorders` | Backorder tracking |
| `inv_demand_history` | Demand records for forecasting |
| `inv_supplier_leadtimes` | Supplier lead time data |
| `inv_reorder_suggestions` | Auto-generated reorder recommendations |

## Optional: Cart Integration

For cart integration features:

```bash
composer require aiarmada/cart
```

Then in `config/inventory.php`, enable cart features:

```php
'cart' => [
    'validate_on_add' => true,
    'auto_allocate_on_add' => true,
    'allocation_ttl_minutes' => 30,
    'allow_backorder' => false,
],
```

## Optional: Multi-Tenancy

To enable owner-scoped inventory:

```php
// config/inventory.php
'owner' => [
    'enabled' => true,
    'include_global' => false, // Whether to include global inventory
],
```

Ensure you have bound `OwnerResolverInterface` in your service container.

## Make Models Inventoryable

Add the `HasInventory` trait to any model that should track inventory:

```php
use AIArmada\Inventory\Traits\HasInventory;

class Product extends Model
{
    use HasInventory;
}
```

This provides:
- `$product->inventoryLevels()` - Stock levels across locations
- `$product->inventoryMovements()` - Movement history
- `$product->inventoryAllocations()` - Current allocations
- `$product->batches()` - Batch/lot records
- `$product->serials()` - Serial numbers
- And helper methods like `getAvailableStock()`, `isInStock()`, etc.

## Verify Installation

```php
use AIArmada\Inventory\Facades\Inventory;

// Create a location
$location = \AIArmada\Inventory\Models\InventoryLocation::create([
    'name' => 'Main Warehouse',
    'code' => 'WH-001',
    'is_active' => true,
]);

// Receive some inventory
Inventory::receive($product, 100, $location->id);

// Check it worked
echo $product->getAvailableStock(); // 100
```

## Next Steps

- [Configuration](03-configuration.md) - Customize all options
- [Usage](04-usage.md) - Learn the API
- [Troubleshooting](99-troubleshooting.md) - Common issues
