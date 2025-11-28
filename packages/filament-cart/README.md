# Filament Cart

Filament admin panel integration for `aiarmada/cart`. Provides normalized cart data resources, condition management, and real-time synchronization for high-volume commerce operations.

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament 5+
- aiarmada/cart

## Installation

```bash
composer require aiarmada/filament-cart
```

Register the plugin in your Filament panel provider:

```php
use AIArmada\FilamentCart\FilamentCartPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentCartPlugin::make(),
        ]);
}
```

Publish the configuration (optional):

```bash
php artisan vendor:publish --tag="filament-cart-config"
```

## Resources

The plugin registers four Filament resources under the **E-Commerce** navigation group:

| Resource | Model | Purpose |
|----------|-------|---------|
| Carts | `Cart` | View normalized cart snapshots |
| Cart Items | `CartItem` | Browse individual line items |
| Cart Conditions | `CartCondition` | View conditions applied to carts |
| Conditions | `Condition` | Manage reusable condition templates |

### Cart Resource

Read-only resource displaying cart snapshots with:

- Instance filtering (default, wishlist, quote, layaway)
- Item and condition counts
- Subtotal and total calculations
- Metadata search

### Cart Item Resource

Read-only resource for line item analysis:

- Price and quantity filters
- Attribute inspection
- Parent cart navigation
- JSON-searchable metadata

### Cart Condition Resource

Read-only resource showing applied conditions:

- Type badges (discount, fee, tax, shipping)
- Target visualization (subtotal, total, item)
- Value display (percentage or fixed)
- Calculation order

### Condition Resource

Full CRUD for reusable condition templates:

- Create discount, tax, fee, and shipping conditions
- Configure dynamic rules with factory keys
- Set global auto-apply behavior
- Manage condition ordering

## Widget

The `CartStatsWidget` displays key metrics on your dashboard:

- Active cart count
- Total cart items
- Total cart value

## Configuration

```php
// config/filament-cart.php

return [
    'navigation_group' => 'E-Commerce',
    'polling_interval' => 30,
    'enable_global_conditions' => true,
    'dynamic_rules_factory' => \AIArmada\Cart\Services\BuiltInRulesFactory::class,
    
    'synchronization' => [
        'queue_sync' => false,
        'queue_connection' => 'default',
        'queue_name' => 'cart-sync',
    ],
];
```

## Event Synchronization

The plugin automatically syncs cart state via event listeners:

- `SyncCartOnEvent` — Creates/updates normalized records
- `ApplyGlobalConditions` — Auto-applies global conditions
- `CleanupSnapshotOnCartMerged` — Handles cart merge cleanup

## Dynamic Conditions

Create conditions with rule-based application:

```php
use AIArmada\Cart\Models\Condition;

Condition::create([
    'name' => 'bulk_discount',
    'display_name' => '10% Bulk Discount',
    'type' => 'discount',
    'target' => 'cart@cart_subtotal/aggregate',
    'value' => '-10%',
    'rules' => [
        'factory_keys' => ['min-items', 'total-at-least'],
        'context' => ['min' => 3, 'amount' => 10000],
    ],
    'is_active' => true,
    'is_global' => true,
]);
```

## Actions

Filament actions for cart operations:

- `ApplyConditionAction` — Apply a condition template to a cart
- `RemoveConditionAction` — Remove a condition from a cart

## Testing

```bash
./vendor/bin/pest tests/src/FilamentCart --parallel
```

## License

MIT License. See [LICENSE](LICENSE) for details.
