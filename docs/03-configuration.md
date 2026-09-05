---
title: Configuration
---

# Configuration

After publishing the config file (`php artisan vendor:publish --tag=inventory-config`), you'll find all options in `config/inventory.php`.

## Database

```php
'database' => [
    'table_prefix' => 'inventory_',
    'tables' => [
        'locations' => 'inventory_locations',
        'levels' => 'inventory_levels',
        'movements' => 'inventory_movements',
        // ... all table names
    ],
],
```

| Option | Default | Description |
|--------|---------|-------------|
| `table_prefix` | `inventory_` | Prefix for all inventory tables |
| `tables` | Array | Override individual table names |

## Defaults

```php
'defaults' => [
    'currency' => 'MYR',
],

'default_reorder_point' => 10,
'allocation_strategy' => 'priority',
'allocation_ttl_minutes' => 30,
'allow_split_allocation' => true,
```

| Option | Default | Description |
|--------|---------|-------------|
| `currency` | `MYR` | Default currency for cost tracking |
| `default_reorder_point` | `10` | Stock level triggering reorder alerts |
| `allocation_strategy` | `priority` | How to select locations when allocating |
| `allocation_ttl_minutes` | `30` | How long cart allocations last |
| `allow_split_allocation` | `true` | Allow splitting orders across locations |

## Product model integration

The checkout bridge uses the Products package models by default when that optional package is installed. Applications with replacement product models can publish the config and set one or both overrides:

```php
'models' => [
    'product' => App\Models\Product::class,
    'variant' => App\Models\Variant::class,
],
```

Leave either value as `null` to use the corresponding `AIArmada\Products` model. The Inventory package does not read model overrides from Checkout or Products config files.

### Allocation Strategies

| Strategy | Description |
|----------|-------------|
| `priority` | Allocate from highest-priority locations first |
| `fifo` | Allocate from locations with oldest stock first |
| `least_stock` | Allocate to balance inventory across locations |
| `single_location` | Fulfill from one location or fail |

## Multi-Tenancy (Owner)

```php
'owner' => [
    'enabled' => false,
    'include_global' => false,
    'auto_assign_on_create' => true,
],
```

| Option | Default | Description |
|--------|---------|-------------|
| `enabled` | `false` | Enable owner-scoped inventory |
| `include_global` | `false` | Include global (owner=null) records in queries |
| `auto_assign_on_create` | `true` | Auto-assign current owner to new locations |

When enabled, all inventory operations are automatically scoped to the current owner (tenant/team). You must bind `OwnerResolverInterface` to resolve the current owner.

## Cart Integration

```php
'cart' => [
    'enabled' => true,
    'validate_on_add' => false,
    'auto_allocate_on_add' => false,
    'reserve_on_checkout' => true,
    'block_checkout_on_insufficient' => true,
    'allow_backorder' => false,
    'max_backorder_quantity' => null,
    'allocation_metadata_key' => 'inventory_allocated',
    'backorder_metadata_key' => 'is_backorder',
],
```

| Option | Default | Description |
|--------|---------|-------------|
| `enabled` | `true` | Enable cart integration |
| `validate_on_add` | `false` | Validate stock when adding to cart |
| `auto_allocate_on_add` | `false` | Reserve stock immediately on add |
| `reserve_on_checkout` | `true` | Reserve stock at checkout start |
| `block_checkout_on_insufficient` | `true` | Block checkout if insufficient stock |
| `allow_backorder` | `false` | Allow adding out-of-stock items |
| `max_backorder_quantity` | `null` | Max backorder qty per item (null=unlimited) |

## Payment Integration

```php
'payment' => [
    'auto_commit' => true,
    'events' => [],
],
```

| Option | Default | Description |
|--------|---------|-------------|
| `auto_commit` | `true` | Auto-commit allocations on payment success |
| `events` | `[]` | Custom payment events to listen for |

By default, the package listens to Cashier/CashierChip payment events. Add custom events to the `events` array.

## Events

```php
'events' => [
    'low_inventory' => true,
    'out_of_inventory' => true,
],
```

| Option | Default | Description |
|--------|---------|-------------|
| `low_inventory` | `true` | Dispatch `LowInventoryDetected` events |
| `out_of_inventory` | `true` | Dispatch `OutOfInventory` events |

## Cleanup

```php
'cleanup' => [
    'keep_expired_for_minutes' => 0,
],
```

| Option | Default | Description |
|--------|---------|-------------|
| `keep_expired_for_minutes` | `0` | Keep expired allocations for this long before cleanup |
