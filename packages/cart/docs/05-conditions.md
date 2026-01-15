---
title: Condition System
---

# Condition System

The Cart package features a sophisticated condition system for discounts, fees, taxes, and shipping with precise control over when and how they apply.

## Core Concepts

### Condition Target

Every condition has a **target** that defines:
- **Scope**: Where it applies (cart-level or item-level)
- **Phase**: When it applies in the calculation pipeline
- **Application**: How it applies to amounts

### Target DSL

Targets can be expressed as a DSL string:

```
scope@phase/application
```

Examples:
- `cart@CART_SUBTOTAL/aggregate` - Apply once to subtotal
- `items@ITEM_DISCOUNT/per_item` - Apply to each item
- `cart@TAX/aggregate` - Apply to taxable amount

## Creating Conditions

### Using CartCondition Directly

```php
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Target;

$condition = new CartCondition(
    name: '10% Off',
    type: 'discount',
    target: Target::cart()->phase(ConditionPhase::CART_SUBTOTAL)->build(),
    value: '-10%',
    attributes: ['description' => 'Summer sale discount'],
    order: 1
);

Cart::addCondition($condition);
```

### Using Presets

```php
use AIArmada\Cart\Conditions\Presets\ConditionPresets;

// Percentage discount
$discount = ConditionPresets::percentageDiscount(
    name: 'Member Discount',
    percentage: 15,
    target: Target::items()->build()
);

// Fixed discount
$fixed = ConditionPresets::fixedDiscount(
    name: '$5 Off',
    amountCents: 500
);

// Tax
$tax = ConditionPresets::tax(
    name: 'Sales Tax',
    percentage: 7
);

// Shipping
$shipping = ConditionPresets::flatShipping(
    name: 'Standard',
    amountCents: 599
);

Cart::addCondition($discount);
Cart::addCondition($tax);
```

## Pipeline Phases

Conditions execute in strict order:

```php
use AIArmada\Cart\Conditions\Enums\ConditionPhase;

ConditionPhase::PRE_ITEM       // Order 10 - Before items
ConditionPhase::ITEM_DISCOUNT  // Order 20 - Item discounts
ConditionPhase::ITEM_POST      // Order 30 - After item discounts
ConditionPhase::CART_SUBTOTAL  // Order 40 - Subtotal adjustments
ConditionPhase::SHIPPING       // Order 50 - Shipping fees
ConditionPhase::TAXABLE        // Order 60 - Pre-tax adjustments
ConditionPhase::TAX            // Order 70 - Tax calculations
ConditionPhase::PAYMENT        // Order 80 - Payment fees
ConditionPhase::GRAND_TOTAL    // Order 90 - Final adjustments
ConditionPhase::CUSTOM         // Order 100 - Custom phase
```

### Phase Selection

```php
// Discount before tax
$discount = new CartCondition(
    name: 'Pre-tax Discount',
    type: 'discount',
    target: Target::cart()->phase(ConditionPhase::CART_SUBTOTAL)->build(),
    value: '-10%'
);

// Fee after tax
$fee = new CartCondition(
    name: 'Processing Fee',
    type: 'fee',
    target: Target::cart()->phase(ConditionPhase::GRAND_TOTAL)->build(),
    value: '+199'
);
```

## Application Strategies

```php
use AIArmada\Cart\Conditions\Enums\ConditionApplication;

// AGGREGATE: Apply once to total amount
Target::cart()->applyAggregate()->build();

// PER_ITEM: Apply to each line item's total
Target::items()->applyPerItem()->build();

// PER_UNIT: Apply to each unit (quantity)
Target::items()->applyPerUnit()->build();

// PER_GROUP: Apply to grouped items
Target::items()->applyPerGroup('category')->build();
```

### Application Examples

```php
// $5 off total
$condition = new CartCondition(
    name: 'Cart Discount',
    type: 'discount',
    target: Target::cart()->applyAggregate()->build(),
    value: '-500'
);

// $1 off each item
$condition = new CartCondition(
    name: 'Per Item Discount',
    type: 'discount',
    target: Target::items()->applyPerItem()->build(),
    value: '-100'
);

// $0.50 off each unit
$condition = new CartCondition(
    name: 'Per Unit Discount',
    type: 'discount',
    target: Target::items()->applyPerUnit()->build(),
    value: '-50'
);
```

## Condition Scopes

### Cart Scope

Applies to the entire cart:

```php
$condition = new CartCondition(
    name: 'Cart Discount',
    type: 'discount',
    target: Target::cart()->build(),
    value: '-10%'
);
```

### Items Scope

Applies to items:

```php
$condition = new CartCondition(
    name: 'Item Discount',
    type: 'discount',
    target: Target::items()->build(),
    value: '-5%'
);
```

### Filtered Items

Apply to specific items:

```php
use AIArmada\Cart\Conditions\Enums\ConditionFilterOperator;

// Filter by attribute
$condition = new CartCondition(
    name: 'Category Discount',
    type: 'discount',
    target: Target::items()
        ->where('category', ConditionFilterOperator::EQUALS, 'electronics')
        ->build(),
    value: '-15%'
);

// Multiple filters
$condition = new CartCondition(
    name: 'Premium Discount',
    type: 'discount',
    target: Target::items()
        ->where('category', ConditionFilterOperator::IN, ['premium', 'gold'])
        ->where('price', ConditionFilterOperator::GREATER_THAN, 5000)
        ->build(),
    value: '-20%'
);
```

## Condition Values

### Percentage Values

```php
'-10%'   // 10% discount
'+7%'    // 7% surcharge
'-15.5%' // 15.5% discount
```

### Fixed Values (in cents)

```php
'-500'   // $5.00 discount
'+299'   // $2.99 fee
'1500'   // $15.00 (implicit +)
```

### Multipliers

```php
'*0.9'   // Multiply by 0.9 (10% off)
'*1.15'  // Multiply by 1.15 (15% markup)
'/2'     // Divide by 2 (50% off)
```

## Condition Presets

The `ConditionPresets` class provides 30+ ready-to-use conditions:

### Discounts

```php
ConditionPresets::percentageDiscount('Sale', 10);
ConditionPresets::fixedDiscount('$5 Off', 500);
ConditionPresets::tieredDiscount('Volume', [[100, 5], [500, 10], [1000, 15]]);
ConditionPresets::buyXGetYFree('BOGO', 2, 1);
ConditionPresets::percentageDiscountCapped('Max $50', 20, 5000);
ConditionPresets::flashSaleDiscount('Flash', 25, '2024-01-15', '2024-01-16');
```

### Fees & Charges

```php
ConditionPresets::fixedFee('Handling', 199);
ConditionPresets::percentageFee('Commission', 2.5);
```

### Shipping

```php
ConditionPresets::flatShipping('Standard', 599);
ConditionPresets::freeShippingOver('Free Ship', 5000);
ConditionPresets::percentageShipping('Rate', 5);
ConditionPresets::tieredShipping('Weight', [[1000, 599], [5000, 999], [10000, 1499]]);
```

### Tax

```php
ConditionPresets::tax('Sales Tax', 7);
ConditionPresets::inclusiveTax('VAT', 20);
ConditionPresets::compoundTax('Combined', [['State', 6], ['Local', 2]]);
```

## Item-Level Conditions

Add conditions to specific items:

```php
// Add to item
Cart::addItemCondition('SKU-001', new CartCondition(
    name: 'Item Discount',
    type: 'discount',
    target: Target::items()->build(),
    value: '-10%'
));

// Remove from item
Cart::removeItemCondition('SKU-001', 'Item Discount');

// Clear all item conditions
Cart::clearItemConditions('SKU-001');
```

## Percentage Rate Arithmetic

Percentages use **basis points** (1/100th of a percent) internally for integer arithmetic:

```php
use AIArmada\Cart\Conditions\PercentageRate;

$rate = PercentageRate::fromPercentString('-10%');
// Internally: -1000 basis points

$result = $rate->apply(10000); // $100.00 in cents
// Returns: 9000 (10% off = $90.00)

// Scale is 10000 (100% = 10000 bp)
$rate->basisPoints; // -1000
$rate->isDiscount(); // true
$rate->isCharge(); // false
```

## Pipeline Results

Get detailed breakdown of condition calculations:

```php
$result = Cart::evaluatePipelineWithCaching();

$result->initialAmount; // Starting amount
$result->finalAmount;   // After all conditions
$result->subtotal();    // After subtotal phase
$result->total();       // Grand total

// Per-phase breakdown
foreach ($result->phaseResults as $phase => $phaseResult) {
    echo "{$phase}: {$phaseResult->inputAmount} → {$phaseResult->outputAmount}";
}
```
