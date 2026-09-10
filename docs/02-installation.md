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
    'allow_backorder' => false,
],
'allocation_ttl_minutes' => 30,
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
- `$product->batches()` - Batch/lot records
- `$product->serials()` - Serial numbers
- And read helpers such as `getTotalAvailable()` and `hasInventory()`

Stock mutations belong to `InventoryService`; cart allocations belong to
`InventoryAllocationService` or the checkout reservation contract.

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
Inventory::receive($product, $location->id, 100);

// Check it worked
echo $product->getTotalAvailable(); // 100
```

## Extending Contracts and Registries

The package defines **6 contracts** in `Contracts/` that you can implement to extend behaviour:

| Contract | Purpose |
|----------|---------|
| `InventoryableInterface` | Make any model inventory-trackable |
| `CheckoutInventoryServiceInterface` | Simplified checkout integration |
| `CostingMethodInterface` | Built-in costing adapter contract |
| `ExportInterface` | Custom export formats |
| `ReportInterface` | Custom report types |

Export and report implementations can be registered through the **Support registries** in `Support/`:

```php
use AIArmada\Inventory\Support\ExportRegistry;
use AIArmada\Inventory\Support\ReportRegistry;
```

Costing adapters are named services under `Services/Costing/` and are wired directly into `ValuationService`. Adding a new costing method requires updating that service's explicit method map alongside its adapter.

The export and report registries provide `register()` and `get()` methods, making those surfaces extensible without modifying core code.

> [!WARNING]
> `CostingMethodRegistry` and `AllocationStrategyRegistry` are removed. Migrate costing integrations to `ValuationService` and keep custom costing adapters in its explicit method map; allocation uses the `AllocationStrategy` enum and service match directly.

## Next Steps

- [Configuration](03-configuration.md) - Customize all options
- [Usage](04-usage.md) - Learn the API
- [Troubleshooting](99-troubleshooting.md) - Common issues
