---
title: Cart Package Overview
---

# Cart Package Overview

The Cart package is a comprehensive, enterprise-grade shopping cart solution for Laravel applications. It provides a sophisticated condition pipeline system, multi-tenancy support, and multiple storage backends.

## Core Architecture

### Design Philosophy

1. **Integer Arithmetic** - All monetary values are stored and computed in **cents** (integer) to avoid floating-point precision issues. The `Akaunting\Money` library is used only for display formatting.

2. **Immutability** - `CartItem` is a `readonly` class. Updates return new instances via `withBatch()` for efficient multi-property modifications.

3. **Pipeline-Based Conditions** - Conditions flow through a 10-phase pipeline with strict ordering, enabling complex discount/fee/tax scenarios.

4. **Lazy Evaluation** - The `LazyConditionPipeline` memoizes calculations, only recomputing when cart state changes.

5. **Multi-Tenancy Ready** - Uses `HasOwner` trait from `commerce-support` for owner-scoped data isolation.

## Key Components

### Cart Class

The main `Cart` class composes 8 traits:

| Trait | Responsibility |
|-------|----------------|
| `ManagesItems` | Add, update, remove items |
| `ManagesConditions` | Static condition management |
| `ManagesDynamicConditions` | Rule-based auto-apply conditions |
| `CalculatesTotals` | Subtotal, total, savings |
| `HasLazyPipeline` | Memoized pipeline evaluation |
| `ManagesStorage` | Persistence layer abstraction |
| `ManagesMetadata` | Key-value metadata storage |
| `ManagesInstances` | Multiple cart instances (wishlist, etc.) |

### Condition System

The condition system is the most sophisticated part of the package:

```
ConditionTarget (DSL Parser)
    ↓
ConditionPhase (10 ordered phases)
    ↓
ConditionApplication (4 application strategies)
    ↓
LazyConditionPipeline (memoized evaluation)
    ↓
Result (subtotal, total, per-phase breakdown)
```

### Storage Layer

Two storage backends:

- **DatabaseStorage** - Production use with optimistic locking (CAS), TTL support, multi-tenant scoping
- **SessionStorage** - Development/testing, same interface

## Features

### Static Conditions

Pre-defined discounts, fees, taxes, shipping added directly:

```php
$cart->addCondition(new CartCondition(
    name: 'Summer Sale',
    type: 'discount',
    target: Target::cart()->build(),
    value: '-10%'
));
```

### Dynamic Conditions

Rule-based conditions that auto-apply when criteria are met:

```php
$cart->registerDynamicCondition(
    condition: ConditionPresets::percentageDiscount(
        name: 'Bulk Discount',
        percentage: 15,
        target: Target::items()->build()
    ),
    rules: RulePresets::minimumQuantity(10)
);
```

### Condition Presets

30+ ready-to-use condition factories in `ConditionPresets`:

- `percentageDiscount()` - Percentage off
- `fixedDiscount()` - Fixed amount off
- `freeShippingOver()` - Free shipping threshold
- `tieredDiscount()` - Volume-based pricing
- `flashSaleDiscount()` - Time-limited offers
- `buyXGetYFree()` - BOGO promotions
- And many more...

### Rule Presets

Pre-built validation rules in `RulePresets`:

- `minimumCartValue()` - Cart value threshold
- `minimumQuantity()` - Item quantity threshold
- `requireProduct()` - Specific product required
- `dateRange()` - Date-based validity
- `requireCustomerTag()` - Customer segmentation
- And many more...

## Pipeline Phases

Conditions execute in this order:

| Phase | Order | Purpose |
|-------|-------|---------|
| `PRE_ITEM` | 10 | Before item calculations |
| `ITEM_DISCOUNT` | 20 | Item-level discounts |
| `ITEM_POST` | 30 | After item discounts |
| `CART_SUBTOTAL` | 40 | Subtotal-level adjustments |
| `SHIPPING` | 50 | Shipping fees |
| `TAXABLE` | 60 | Pre-tax adjustments |
| `TAX` | 70 | Tax calculations |
| `PAYMENT` | 80 | Payment fees |
| `GRAND_TOTAL` | 90 | Final adjustments |
| `CUSTOM` | 100 | Custom phase |

## Application Strategies

How conditions are applied to amounts:

| Strategy | Description |
|----------|-------------|
| `AGGREGATE` | Apply once to total |
| `PER_ITEM` | Apply to each line item |
| `PER_UNIT` | Apply to each unit quantity |
| `PER_GROUP` | Apply to grouped items |

## Events

All cart operations dispatch events:

- `CartCreated` - First item added
- `CartCleared` - Cart emptied
- `CartDestroyed` - Cart removed from storage
- `CartMerged` - Guest cart merged on login
- `ItemAdded`, `ItemUpdated`, `ItemRemoved`
- `CartConditionAdded`, `CartConditionRemoved`
- `ItemConditionAdded`, `ItemConditionRemoved`
- `MetadataAdded`, `MetadataRemoved`, `MetadataCleared`

Events are dispatched after database transactions via `DB::afterCommit()`.

## Multi-Tenancy

Enable owner scoping in config:

```php
'owner' => [
    'enabled' => true,
    'include_global' => false,
],
```

Then scope operations:

```php
$cart = Cart::forOwner($tenant)->instance('default');
```

## Configuration

Key configuration options in `config/cart.php`:

```php
return [
    'database' => [
        'table' => 'carts',
        'conditions_table' => 'conditions',
        'ttl' => 60 * 60 * 24 * 30, // 30 days
    ],
    'money' => [
        'default_currency' => 'MYR',
        'rounding_mode' => 'half_up',
    ],
    'empty_cart_behavior' => 'destroy', // destroy, clear, preserve
    'migration' => [
        'auto_migrate_on_login' => true,
        'merge_strategy' => 'add_quantities',
    ],
    'performance' => [
        'lazy_pipeline' => true,
    ],
];
```

## Package Dependencies

- `akaunting/money` - Money formatting
- `commerce-support` - Owner scoping, Money normalization
- Laravel 11+ with PHP 8.4+
