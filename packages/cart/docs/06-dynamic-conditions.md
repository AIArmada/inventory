---
title: Dynamic Conditions
---

# Dynamic Conditions

Dynamic conditions are rule-based conditions that automatically apply or remove themselves based on cart state. Unlike static conditions that are always active, dynamic conditions evaluate rules each time totals are calculated.

## Concept

Dynamic conditions enable scenarios like:
- "10% off when cart total exceeds $100"
- "Free shipping for VIP members"
- "Happy hour discount (3-5 PM)"
- "Buy 3+ items, get 15% off"

## Registering Dynamic Conditions

### Using Rules Factory

```php
use AIArmada\Cart\Conditions\Presets\ConditionPresets;
use AIArmada\Cart\Services\RulePresets;

Cart::setRulesFactory(app(RulesFactoryInterface::class));

Cart::registerDynamicCondition(
    condition: ConditionPresets::percentageDiscount(
        name: 'Bulk Discount',
        percentage: 15
    ),
    rules: RulePresets::minimumCartValue(10000) // $100 minimum
);
```

### Using Factory Keys

Factory keys allow rules to be persisted and restored across requests:

```php
Cart::registerDynamicCondition(
    condition: ConditionPresets::percentageDiscount('VIP Discount', 20),
    ruleFactoryKey: 'customer-tag',
    metadata: ['tag' => 'vip']
);
```

### With Multiple Rules

```php
Cart::registerDynamicCondition(
    condition: ConditionPresets::freeShippingOver('Free Shipping', 0),
    rules: array_merge(
        RulePresets::minimumCartValue(5000),
        RulePresets::requireWeekend()
    )
);
```

## Rule Presets

The `RulePresets` class provides pre-built rules:

### Cart Value Rules

```php
RulePresets::minimumCartValue(5000);      // Min $50
RulePresets::maximumCartValue(100000);    // Max $1000
RulePresets::cartValueBetween(5000, 10000); // $50-$100
```

### Quantity Rules

```php
RulePresets::minimumQuantity(5);          // At least 5 items
RulePresets::maximumQuantity(20);         // No more than 20
RulePresets::minimumItems(3);             // At least 3 different products
RulePresets::maximumItems(10);            // No more than 10 products
```

### Product Rules

```php
RulePresets::requireProduct('SKU-001');   // Must have product
RulePresets::excludeProduct('GIFT-CARD'); // Must NOT have product
RulePresets::requireAnyProduct(['A', 'B', 'C']); // Any of these
RulePresets::requireAllProducts(['A', 'B']);     // All of these
RulePresets::requireProductPrefix('PROMO-');     // ID starts with
```

### Time-Based Rules

```php
RulePresets::dateRange('2024-01-01', '2024-01-31'); // January only
RulePresets::timeWindow('15:00', '17:00');          // 3-5 PM
RulePresets::requireWeekend();                       // Sat/Sun only
RulePresets::requireWeekday();                       // Mon-Fri only
RulePresets::requireDaysOfWeek(['monday', 'wednesday', 'friday']);
```

### Customer Rules

```php
RulePresets::requireCustomerTag('vip');
RulePresets::requireAnyCustomerTag(['gold', 'platinum']);
RulePresets::requireVip();
RulePresets::requireAuthenticated();
```

### Metadata Rules

```php
RulePresets::requireMetadata('coupon_applied');
RulePresets::requireMetadataValue('channel', 'mobile');
RulePresets::requireFlag('first_order');
RulePresets::blockIfFlag('discount_used');
```

### Cart State Rules

```php
RulePresets::requireNonEmpty();
RulePresets::blockIfConditionExists('OTHER-DISCOUNT');
RulePresets::requireCondition('SHIPPING-SELECTED');
RulePresets::blockIfConditionTypeExists('discount');
```

### Utility Rules

```php
RulePresets::always();  // Always pass
RulePresets::never();   // Always fail

// Combine rules with AND logic
RulePresets::all(
    RulePresets::minimumCartValue(5000),
    RulePresets::requireWeekend()
);

// Combine rules with OR logic
RulePresets::any(
    RulePresets::requireCustomerTag('vip'),
    RulePresets::minimumCartValue(10000)
);

// Negate rules
RulePresets::not(RulePresets::requireWeekend()); // NOT weekend
```

## Built-In Rules Factory

The `BuiltInRulesFactory` provides 40+ factory keys for persistence:

```php
// Value-based
'subtotal-at-least', 'subtotal-below', 'subtotal-between'
'total-at-least', 'total-below', 'total-between'

// Quantity-based
'min-items', 'max-items'
'min-quantity', 'max-quantity'

// Product-based
'has-any-item', 'has-item', 'missing-item'
'item-list-includes-any', 'item-list-includes-all'

// Metadata-based
'has-metadata', 'metadata-equals', 'metadata-not-equals'
'metadata-in', 'metadata-contains', 'metadata-flag-true'

// Customer-based
'customer-tag', 'currency-is'

// Time-based
'day-of-week', 'date-window', 'time-window'

// Item attribute rules
'item-attribute-equals', 'item-attribute-in'
'item-quantity-at-least', 'item-quantity-at-most'
'item-price-at-least', 'item-price-at-most'
'item-total-at-least', 'item-total-at-most'
'item-has-condition', 'item-id-prefix'

// Condition-based
'cart-condition-exists', 'cart-condition-type-exists'

// Utility
'always-true', 'always-false'
```

### Using Factory Keys

```php
Cart::registerDynamicCondition(
    condition: ConditionPresets::percentageDiscount('Weekend Sale', 20),
    ruleFactoryKey: 'day-of-week',
    metadata: ['days' => ['saturday', 'sunday']]
);

Cart::registerDynamicCondition(
    condition: ConditionPresets::freeShippingOver('Free Ship', 0),
    ruleFactoryKey: 'subtotal-at-least',
    metadata: ['amount' => 7500] // $75
);
```

### Multiple Factory Keys

```php
Cart::registerDynamicCondition(
    condition: ConditionPresets::percentageDiscount('VIP Weekend', 25),
    ruleFactoryKey: ['customer-tag', 'day-of-week'],
    metadata: [
        'tag' => 'vip',
        'days' => ['saturday', 'sunday']
    ]
);
```

## Evaluation Lifecycle

Dynamic conditions follow a dirty-flag pattern:

1. **Cart Modified** → Conditions marked dirty
2. **Totals Requested** → Conditions evaluated if dirty
3. **Rules Checked** → Each condition's rules evaluated
4. **Conditions Applied/Removed** → Based on rule results
5. **Cache Updated** → Results memoized

```php
// Manual evaluation
Cart::evaluateDynamicConditions();

// Check if dirty
if (Cart::isDynamicConditionsDirty()) {
    // Conditions need re-evaluation
}

// Force mark dirty
Cart::markDynamicConditionsDirty();

// Evaluate only if dirty
Cart::evaluateDynamicConditionsIfDirty();
```

## Item-Scoped Dynamic Conditions

Apply conditions to items that match criteria:

```php
Cart::registerDynamicCondition(
    condition: new CartCondition(
        name: 'Bulk Item Discount',
        type: 'discount',
        target: Target::items()->build(), // ITEMS scope
        value: '-10%',
        rules: [
            fn($cart, $item) => $item && $item->quantity >= 5
        ]
    )
);
```

## Persistence & Restoration

Dynamic conditions with factory keys are persisted to metadata:

```php
// Conditions are automatically persisted when using factory keys
Cart::registerDynamicCondition(
    condition: ConditionPresets::percentageDiscount('Member Discount', 10),
    ruleFactoryKey: 'customer-tag',
    metadata: ['tag' => 'member']
);

// Later, restore conditions (called automatically on cart load)
Cart::restoreDynamicConditions();

// Get persisted metadata
$metadata = Cart::getDynamicConditionMetadata();

// Clear all dynamic conditions
Cart::clearDynamicConditions();
```

## Managing Dynamic Conditions

```php
// Get all registered dynamic conditions
$conditions = Cart::getDynamicConditions();

// Remove specific dynamic condition
Cart::removeDynamicCondition('Weekend Sale');
```

## Error Handling

```php
// Register failure handler
Cart::onDynamicConditionFailure(function ($operation, $condition, $exception, $context) {
    logger()->warning("Dynamic condition failed", [
        'operation' => $operation,
        'condition' => $condition?->getName(),
        'error' => $exception?->getMessage(),
        'context' => $context,
    ]);
});
```

## Complete Example

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Conditions\Presets\ConditionPresets;
use AIArmada\Cart\Services\RulePresets;
use AIArmada\Cart\Services\BuiltInRulesFactory;

// Set up rules factory
Cart::setRulesFactory(new BuiltInRulesFactory());

// Register multiple dynamic conditions
Cart::registerDynamicCondition(
    condition: ConditionPresets::percentageDiscount('Bulk Discount', 15),
    rules: RulePresets::minimumQuantity(10)
);

Cart::registerDynamicCondition(
    condition: ConditionPresets::freeShippingOver('Free Shipping', 0),
    ruleFactoryKey: 'subtotal-at-least',
    metadata: ['amount' => 10000]
);

Cart::registerDynamicCondition(
    condition: ConditionPresets::percentageDiscount('Happy Hour', 20),
    rules: RulePresets::all(
        RulePresets::timeWindow('15:00', '17:00'),
        RulePresets::requireWeekday()
    )
);

// Add items
Cart::add('SKU-001', 'Product', 999, 12); // 12 items

// Get totals - dynamic conditions auto-evaluated
$total = Cart::total(); // Bulk discount applied
```
