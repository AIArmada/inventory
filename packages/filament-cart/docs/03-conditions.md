# Conditions

The Condition model provides reusable pricing rule templates that can be applied to carts. Conditions support static values, percentage calculations, and dynamic rule-based evaluation.

## Condition Model

Located in `AIArmada\Cart\Models\Condition`, the model stores:

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Unique identifier slug |
| `display_name` | string | User-facing label |
| `description` | string | Optional description |
| `type` | string | discount, tax, fee, shipping, surcharge |
| `target` | string | Target expression (e.g., `cart@cart_subtotal/aggregate`) |
| `value` | string | Value expression (+100, -10%, etc.) |
| `order` | int | Calculation sequence (lower = first) |
| `rules` | array | Dynamic rule configuration |
| `is_active` | bool | Whether condition is enabled |
| `is_global` | bool | Auto-apply to all carts |

## Value Syntax

The `value` field supports multiple formats:

| Format | Example | Meaning |
|--------|---------|---------|
| Percentage | `-10%` | 10% discount |
| Positive fixed | `+500` | Add 500 (cents) |
| Negative fixed | `-1000` | Subtract 1000 (cents) |
| Multiply | `*1.1` | Multiply by 1.1 |
| Divide | `/2` | Divide by 2 |

## Target Expressions

Targets define where conditions apply:

| Target | Description |
|--------|-------------|
| `cart@cart_subtotal/aggregate` | Apply to cart subtotal |
| `cart@grand_total/aggregate` | Apply to grand total |
| `items@item_discount/per-item` | Apply per item |

## Creating Conditions

### Basic Discount

```php
use AIArmada\Cart\Models\Condition;

Condition::create([
    'name' => 'summer_sale',
    'display_name' => '20% Summer Sale',
    'type' => 'discount',
    'target' => 'cart@cart_subtotal/aggregate',
    'value' => '-20%',
    'order' => 1,
    'is_active' => true,
]);
```

### Fixed Fee

```php
Condition::create([
    'name' => 'handling_fee',
    'display_name' => 'Handling Fee',
    'type' => 'fee',
    'target' => 'cart@grand_total/aggregate',
    'value' => '+300', // RM3.00 in cents
    'order' => 10,
    'is_active' => true,
]);
```

### Tax

```php
Condition::create([
    'name' => 'sales_tax',
    'display_name' => '6% Sales Tax',
    'type' => 'tax',
    'target' => 'cart@cart_subtotal/aggregate',
    'value' => '6%',
    'order' => 5,
    'is_active' => true,
]);
```

## Dynamic Conditions

Dynamic conditions use rules to determine when they apply.

### Rule Configuration

```php
Condition::create([
    'name' => 'bulk_discount',
    'display_name' => 'Bulk Purchase Discount',
    'type' => 'discount',
    'target' => 'cart@cart_subtotal/aggregate',
    'value' => '-15%',
    'rules' => [
        'factory_keys' => ['min-items', 'total-at-least'],
        'context' => [
            'min' => 5,        // Minimum 5 distinct items
            'amount' => 50000, // Minimum RM500 subtotal
        ],
    ],
    'is_active' => true,
    'is_dynamic' => true,
]);
```

### Available Rule Factories

The cart package provides built-in rule factories:

| Factory Key | Context | Description |
|-------------|---------|-------------|
| `min-items` | `min` | Minimum distinct item count |
| `total-at-least` | `amount` | Minimum cart subtotal |

Custom factories can be registered via `RulesFactoryInterface`.

## Global Conditions

Global conditions auto-apply to all carts:

```php
Condition::create([
    'name' => 'free_shipping_over_100',
    'display_name' => 'Free Shipping (Orders RM100+)',
    'type' => 'shipping',
    'target' => 'cart@grand_total/aggregate',
    'value' => '-0', // Negates shipping cost
    'rules' => [
        'factory_keys' => ['total-at-least'],
        'context' => ['amount' => 10000],
    ],
    'is_active' => true,
    'is_global' => true,
]);
```

The `ApplyGlobalConditions` listener automatically:
- Applies matching global conditions to new carts
- Re-evaluates conditions when items change
- Removes conditions when rules no longer match

## Condition Scopes

The model provides query scopes:

```php
// Active conditions only
Condition::active()->get();

// Global conditions
Condition::global()->get();

// By type
Condition::ofType('discount')->get();

// Dynamic conditions
Condition::dynamic()->get();

// Discounts only
Condition::discounts()->get();

// Charges/fees only
Condition::charges()->get();
```

## Converting to CartCondition

Apply a Condition template to a cart:

```php
$condition = Condition::find($id);

// Get as CartCondition instance
$cartCondition = $condition->createCondition();

// Or as array for cart methods
$conditionData = $condition->toConditionArray();

// Apply to cart
Cart::condition($cartCondition);
```
