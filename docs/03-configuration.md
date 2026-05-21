---
title: Configuration
---

# Configuration

After publishing the config file (`php artisan vendor:publish --tag=inventory-config`), you'll find all options in `config/inventory.php`.

## Database

```php
'database' => [
    'table_prefix' => env('INVENTORY_TABLE_PREFIX', 'inventory_'),
    'json_column_type' => env('INVENTORY_JSON_COLUMN_TYPE', 'json'),
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
| `json_column_type` | `json` | Column type for JSON fields (`json` or `jsonb`) |
| `tables` | Array | Override individual table names |

## Defaults

```php
'defaults' => [
    'currency' => env('INVENTORY_CURRENCY', 'MYR'),
],

'default_reorder_point' => env('INVENTORY_DEFAULT_REORDER_POINT', 10),
'allocation_strategy' => env('INVENTORY_ALLOCATION_STRATEGY', 'priority'),
'allocation_ttl_minutes' => env('INVENTORY_ALLOCATION_TTL', 30),
'allow_split_allocation' => env('INVENTORY_ALLOW_SPLIT', true),
```

| Option | Default | Description |
|--------|---------|-------------|
| `currency` | `MYR` | Default currency for cost tracking |
| `default_reorder_point` | `10` | Stock level triggering reorder alerts |
| `allocation_strategy` | `priority` | How to select locations when allocating |
| `allocation_ttl_minutes` | `30` | How long cart allocations last |
| `allow_split_allocation` | `true` | Allow splitting orders across locations |

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
    'enabled' => env('INVENTORY_OWNER_ENABLED', false),
    'include_global' => env('INVENTORY_OWNER_INCLUDE_GLOBAL', false),
    'auto_assign_on_create' => env('INVENTORY_OWNER_AUTO_ASSIGN_ON_CREATE', true),
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
    'enabled' => env('INVENTORY_CART_ENABLED', true),
    'validate_on_add' => env('INVENTORY_VALIDATE_ON_ADD', false),
    'auto_allocate_on_add' => env('INVENTORY_AUTO_ALLOCATE_ON_ADD', false),
    'reserve_on_checkout' => env('INVENTORY_RESERVE_ON_CHECKOUT', true),
    'block_checkout_on_insufficient' => env('INVENTORY_BLOCK_CHECKOUT_ON_INSUFFICIENT', true),
    'allocation_ttl_minutes' => env('INVENTORY_ALLOCATION_TTL', 30),
    'allow_backorder' => env('INVENTORY_ALLOW_BACKORDER', false),
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
| `allocation_ttl_minutes` | `30` | TTL for cart allocations |
| `allow_backorder` | `false` | Allow adding out-of-stock items |
| `max_backorder_quantity` | `null` | Max backorder qty per item (null=unlimited) |

## Payment Integration

```php
'payment' => [
    'auto_commit' => env('INVENTORY_AUTO_COMMIT', true),
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
    'low_inventory' => env('INVENTORY_EVENT_LOW', true),
    'out_of_inventory' => env('INVENTORY_EVENT_OUT', true),
],
```

| Option | Default | Description |
|--------|---------|-------------|
| `low_inventory` | `true` | Dispatch `LowInventoryDetected` events |
| `out_of_inventory` | `true` | Dispatch `OutOfInventory` events |

## Cleanup

```php
'cleanup' => [
    'keep_expired_for_minutes' => env('INVENTORY_KEEP_EXPIRED', 0),
],
```

| Option | Default | Description |
|--------|---------|-------------|
| `keep_expired_for_minutes` | `0` | Keep expired allocations for this long before cleanup |

## Environment Variables Summary

```bash
# Database
INVENTORY_TABLE_PREFIX=inventory_
INVENTORY_JSON_COLUMN_TYPE=json

# Defaults
INVENTORY_CURRENCY=MYR
INVENTORY_DEFAULT_REORDER_POINT=10
INVENTORY_ALLOCATION_STRATEGY=priority
INVENTORY_ALLOCATION_TTL=30
INVENTORY_ALLOW_SPLIT=true

# Multi-Tenancy
INVENTORY_OWNER_ENABLED=false
INVENTORY_OWNER_INCLUDE_GLOBAL=false
INVENTORY_OWNER_AUTO_ASSIGN_ON_CREATE=true

# Cart
INVENTORY_CART_ENABLED=true
INVENTORY_VALIDATE_ON_ADD=false
INVENTORY_AUTO_ALLOCATE_ON_ADD=false
INVENTORY_RESERVE_ON_CHECKOUT=true
INVENTORY_BLOCK_CHECKOUT_ON_INSUFFICIENT=true
INVENTORY_ALLOW_BACKORDER=false

# Payment
INVENTORY_AUTO_COMMIT=true

# Events
INVENTORY_EVENT_LOW=true
INVENTORY_EVENT_OUT=true

# Cleanup
INVENTORY_KEEP_EXPIRED=0
```
