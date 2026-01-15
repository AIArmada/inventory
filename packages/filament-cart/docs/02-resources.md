---
title: Resources
---

# Filament Resources

The package provides seven Filament resources for comprehensive cart management. All resources appear under the configurable navigation group (default: "E-Commerce").

## Cart Resource

**Route:** `/admin/carts`  
**Model:** `AIArmada\FilamentCart\Models\Cart`  
**Access:** Full CRUD (typically read-only usage)

The Cart Resource displays normalized cart snapshots synchronized from the `aiarmada/cart` package.

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Unique identifier with copy action |
| `identifier` | String | Session/user identifier |
| `instance` | Badge | Cart type (default, wishlist, quote, layaway) |
| `items_count` | Integer | Count of distinct line items |
| `quantity` | Integer | Total quantity across all items |
| `subtotal` | Money | Pre-condition subtotal |
| `total` | Money | Final calculated total |
| `savings` | Money | Total discount amount |
| `last_activity_at` | DateTime | Last cart interaction |
| `checkout_started_at` | DateTime | When checkout began |
| `checkout_abandoned_at` | DateTime | When checkout was abandoned |
| `recovered_at` | DateTime | When cart was recovered |
| `fraud_risk_level` | Badge | Risk assessment (high, medium, low) |
| `created_at` | DateTime | Cart creation timestamp |

### Filters

- **Instance** — Filter by cart type
- **Fraud Risk** — Filter by risk level
- **Abandoned** — Show only abandoned carts
- **Recovered** — Show only recovered carts
- **In Checkout** — Show carts currently in checkout

### Relation Managers

- **ItemsRelationManager** — View cart items
- **ConditionsRelationManager** — View applied conditions

### Query Scoping

```php
public static function getEloquentQuery(): Builder
{
    return Cart::query()->forOwner();
}
```

All queries are automatically scoped to the current owner when multitenancy is enabled.

### Navigation Badge

Shows the count of all carts, colored by status.

### Model Scopes

The Cart model provides useful query scopes:

```php
// Filter by instance
Cart::query()->instance('wishlist')->get();

// Get non-empty carts
Cart::query()->notEmpty()->get();

// Recent carts (last N days)
Cart::query()->recent(7)->get();

// Carts with savings/discounts
Cart::query()->withSavings()->get();

// Abandoned carts
Cart::query()->abandoned()->get();

// Recovered carts
Cart::query()->recovered()->get();

// Carts in checkout
Cart::query()->inCheckout()->get();

// Collaborative carts
Cart::query()->collaborative()->get();

// High fraud risk
Cart::query()->highFraudRisk()->get();

// Needs recovery attention
Cart::query()->needsRecovery()->get();
```

---

## Cart Item Resource

**Route:** `/admin/cart-items`  
**Model:** `AIArmada\FilamentCart\Models\CartItem`  
**Access:** Read-only

Provides analysis of individual line items across all carts.

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Unique identifier |
| `cart` | Relation | Link to parent cart |
| `item_id` | String | Original cart item ID |
| `name` | String | Product/item name |
| `price` | Money | Unit price (in cents) |
| `quantity` | Integer | Item quantity |
| `subtotal` | Money | Calculated line total |
| `attributes` | JSON | Item attributes |
| `conditions` | JSON | Item-level conditions |
| `associated_model` | String | Linked model class |
| `created_at` | DateTime | Creation timestamp |

### Query Scoping

Items are scoped through their parent cart:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->whereIn('cart_id', Cart::query()->forOwner()->select('id'));
}
```

### Model Scopes

```php
// Filter by cart instance
CartItem::query()->instance('default')->get();

// Filter by item name
CartItem::query()->byName('Product')->get();

// Price range (in dollars)
CartItem::query()->priceBetween(10.00, 50.00)->get();

// Quantity range
CartItem::query()->quantityBetween(2, 10)->get();

// Items with conditions
CartItem::query()->withConditions()->get();

// Items without conditions
CartItem::query()->withoutConditions()->get();
```

### Read-Only Protection

```php
public static function canCreate(): bool
{
    return false;
}

public static function canEdit($record): bool
{
    return false;
}

public static function canDelete($record): bool
{
    return false;
}
```

---

## Cart Condition Resource

**Route:** `/admin/cart-conditions`  
**Model:** `AIArmada\FilamentCart\Models\CartCondition`  
**Access:** Read-only

Displays conditions applied to carts (both cart-level and item-level).

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Unique identifier |
| `cart` | Relation | Link to parent cart |
| `cart_item` | Relation | Link to cart item (if item-level) |
| `name` | String | Condition display name |
| `type` | Badge | discount, tax, fee, shipping, surcharge |
| `target` | String | Target expression |
| `value` | String | Value expression (-10%, +500, etc.) |
| `order` | Integer | Calculation sequence |
| `is_percentage` | Boolean | Whether value is percentage |
| `is_discount` | Boolean | Whether condition is a discount |
| `is_charge` | Boolean | Whether condition is a charge |
| `is_global` | Boolean | Auto-applied global condition |
| `is_dynamic` | Boolean | Uses rule-based evaluation |

### Model Scopes

```php
// Cart-level conditions only
CartCondition::query()->cartLevel()->get();

// Item-level conditions only  
CartCondition::query()->itemLevel()->get();

// By type
CartCondition::query()->discounts()->get();
CartCondition::query()->taxes()->get();
CartCondition::query()->fees()->get();
CartCondition::query()->shipping()->get();

// By instance
CartCondition::query()->instance('default')->get();

// By cart identifier
CartCondition::query()->byIdentifier('user-123')->get();

// By type
CartCondition::query()->byType('discount')->get();

// By target
CartCondition::query()->byTarget('cart@cart_subtotal/aggregate')->get();
```

### Helper Methods

```php
$condition->isCartLevel();   // bool
$condition->isItemLevel();   // bool
$condition->isDiscount();    // bool
$condition->isTax();         // bool
$condition->isFee();         // bool
$condition->isShipping();    // bool
$condition->isPercentage();  // bool
$condition->level;           // 'Cart' or 'Item'
$condition->formatted_value; // Formatted display value
```

---

## Condition Resource

**Route:** `/admin/conditions`  
**Model:** `AIArmada\Cart\Models\Condition`  
**Access:** Full CRUD

Manages reusable condition templates from the core cart package.

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| `name` | String | Unique slug identifier |
| `display_name` | String | User-facing label |
| `description` | Text | Optional description |
| `type` | Badge | Condition type |
| `target` | String | Application target |
| `value` | String | Value expression |
| `order` | Integer | Calculation sequence |
| `is_active` | Toggle | Enabled status |
| `is_global` | Toggle | Auto-apply to all carts |
| `is_dynamic` | Badge | Uses rule-based evaluation |

### Form Schema

**Basic Information Section:**
- Name (unique slug)
- Display Name
- Description
- Type (discount, tax, fee, shipping, surcharge)

**Pricing Section:**
- Target expression
- Value (supports +100, -10%, *1.5, /2 syntax)
- Order (calculation sequence, lower = first)

**Rules Section (for Dynamic Conditions):**
- Factory Keys (built-in or custom rule factories)
- Context (key-value configuration for rules)

**Status Section:**
- Is Active
- Is Global

### Target Expressions

| Target | Description |
|--------|-------------|
| `cart@cart_subtotal/aggregate` | Apply to cart subtotal |
| `cart@grand_total/aggregate` | Apply to grand total |
| `items@item_discount/per-item` | Apply per item |

### Value Syntax

| Format | Example | Meaning |
|--------|---------|---------|
| Percentage | `-10%` | 10% discount |
| Positive fixed | `+500` | Add 500 (cents) |
| Negative fixed | `-1000` | Subtract 1000 (cents) |
| Multiply | `*1.1` | Multiply by 1.1 |
| Divide | `/2` | Divide by 2 |

### Creating Conditions

```php
use AIArmada\Cart\Models\Condition;

// 20% discount on cart subtotal
Condition::create([
    'name' => 'summer_sale',
    'display_name' => 'Summer Sale 20% Off',
    'type' => 'discount',
    'target' => 'cart@cart_subtotal/aggregate',
    'value' => '-20%',
    'order' => 1,
    'is_active' => true,
    'is_global' => false,
]);

// 6% tax
Condition::create([
    'name' => 'sales_tax',
    'display_name' => 'Sales Tax (6%)',
    'type' => 'tax',
    'target' => 'cart@cart_subtotal/aggregate',
    'value' => '6%',
    'order' => 10,
    'is_active' => true,
]);

// Dynamic condition with rules
Condition::create([
    'name' => 'bulk_discount',
    'display_name' => 'Bulk Purchase Discount',
    'type' => 'discount',
    'target' => 'cart@cart_subtotal/aggregate',
    'value' => '-15%',
    'rules' => [
        'factory_keys' => ['min-items', 'total-at-least'],
        'context' => [
            'min' => 5,
            'amount' => 10000, // $100 in cents
        ],
    ],
    'is_active' => true,
    'is_global' => true,
]);
```

### Model Scopes

```php
// Active conditions only
Condition::query()->active()->get();

// Global conditions
Condition::query()->global()->get();

// By type
Condition::query()->ofType('discount')->get();

// Dynamic conditions
Condition::query()->dynamic()->get();

// Discounts only
Condition::query()->discounts()->get();

// Charges/fees only
Condition::query()->charges()->get();

// Percentage-based
Condition::query()->percentageBased()->get();

// Item-targeted
Condition::query()->forItems()->get();
```

### Converting to Cart Condition

```php
$condition = Condition::find($id);

// Get as CartCondition instance
$cartCondition = $condition->createCondition();

// Or as array for cart methods
$conditionData = $condition->toConditionArray();

// Apply to cart
Cart::condition($cartCondition);
```

---

## Recovery Campaign Resource

**Route:** `/admin/recovery-campaigns`  
**Model:** `AIArmada\FilamentCart\Models\RecoveryCampaign`  
**Access:** Full CRUD  
**Feature Flag:** `config('filament-cart.features.recovery')`

Manages automated cart abandonment recovery campaigns.

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| `name` | String | Campaign name |
| `status` | Badge | draft, active, paused, completed, archived |
| `strategy` | Badge | email, sms, push, multi_channel |
| `total_sent` | Integer | Messages sent |
| `total_recovered` | Integer | Successful recoveries |
| `conversion_rate` | Percentage | Recovery rate |
| `recovered_revenue_cents` | Money | Total recovered revenue |
| `ab_testing_enabled` | Boolean | A/B testing active |

### Form Sections

**Basic Information:**
- Name, Description, Status

**Trigger Configuration:**
- Trigger Type (abandoned, high_value, exit_intent, custom)
- Trigger Delay (minutes after abandonment)
- Max Attempts (per cart)
- Attempt Interval (hours between attempts)

**Targeting:**
- Min/Max Cart Value (in cents)
- Min/Max Items

**Strategy:**
- Channel (email, sms, push, multi_channel)
- Offer Discount (type: percentage/fixed, value)
- Offer Free Shipping
- Urgency Hours (offer expiry countdown)

**A/B Testing:**
- Enable A/B Testing
- Split Percentage (variant percentage)
- Control Template
- Variant Template

**Schedule:**
- Start Date
- End Date

### Campaign Methods

```php
$campaign->isActive();           // Check if campaign is running
$campaign->getOpenRate();        // Get email open rate
$campaign->getClickRate();       // Get click-through rate
$campaign->getConversionRate();  // Get recovery conversion rate
$campaign->getAverageRecoveredValue(); // Average recovered cart value
```

---

## Recovery Template Resource

**Route:** `/admin/recovery-templates`  
**Model:** `AIArmada\FilamentCart\Models\RecoveryTemplate`  
**Access:** Full CRUD  
**Feature Flag:** `config('filament-cart.features.recovery')`

Manages message templates for recovery campaigns.

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| `name` | String | Template name |
| `type` | Badge | email, sms, push |
| `status` | Badge | draft, active, archived |
| `is_default` | Boolean | Default template for type |
| `times_used` | Integer | Usage count |
| `open_rate` | Percentage | Email open rate |
| `click_rate` | Percentage | Click-through rate |
| `conversion_rate` | Percentage | Recovery conversion rate |

### Template Variables

Templates support variable substitution using `{{variable}}` syntax:

| Variable | Description |
|----------|-------------|
| `{{customer_name}}` | Customer's name |
| `{{cart_total}}` | Formatted cart total |
| `{{cart_url}}` | Link to recover cart |
| `{{discount_code}}` | Generated discount code |
| `{{discount_value}}` | Discount amount |
| `{{expiry_time}}` | Offer expiration time |

### Rendering Templates

```php
$template = RecoveryTemplate::find($id);

$variables = [
    'customer_name' => 'John Doe',
    'cart_total' => '$99.00',
    'cart_url' => 'https://example.com/cart/recover/abc123',
    'discount_code' => 'RECOVER-ABCD-XYZ123',
];

$subject = $template->renderSubject($variables);
$htmlBody = $template->renderHtmlBody($variables);
$textBody = $template->renderTextBody($variables);
$smsBody = $template->renderSmsBody($variables);
$push = $template->renderPush($variables); // Returns array
```

---

## Alert Rule Resource

**Route:** `/admin/alert-rules`  
**Model:** `AIArmada\FilamentCart\Models\AlertRule`  
**Access:** Full CRUD  
**Feature Flag:** `config('filament-cart.features.monitoring')`

Configures alert rules for cart events.

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| `name` | String | Rule name |
| `event_type` | Badge | abandonment, fraud, high_value, recovery, custom |
| `severity` | Badge | info, warning, critical |
| `channels` | String | Enabled notification channels |
| `cooldown_minutes` | Integer | Cooldown between alerts |
| `is_active` | Boolean | Rule enabled |
| `last_triggered_at` | DateTime | Last trigger time |
| `logs_count` | Integer | Total alerts triggered |

### Condition Operators

| Operator | Description |
|----------|-------------|
| `=` | Equals |
| `!=` | Not equals |
| `>` | Greater than |
| `>=` | Greater than or equal |
| `<` | Less than |
| `<=` | Less than or equal |
| `in` | Value in array |
| `not_in` | Value not in array |
| `contains` | String contains |
| `is_null` | Value is null |
| `is_not_null` | Value is not null |
| `between` | Value between range |

### Rule Methods

```php
$rule->isInCooldown();              // Check if in cooldown period
$rule->getCooldownRemainingMinutes(); // Get remaining cooldown
$rule->markTriggered();             // Update last_triggered_at
$rule->getEnabledChannels();        // Get array of enabled channels
```

### Actions

- **Test** — Send test alert to verify all configured channels are working

---

## Extending Resources

Create custom resources by extending the base classes:

```php
namespace App\Filament\Resources;

use AIArmada\FilamentCart\Resources\CartResource as BaseCartResource;
use Filament\Tables\Columns\TextColumn;

class CustomCartResource extends BaseCartResource
{
    protected static ?string $navigationLabel = 'Shopping Carts';
    
    protected static ?string $navigationGroup = 'Shop';
    
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                ...parent::table($table)->getColumns(),
                TextColumn::make('custom_field'),
            ]);
    }
}
```

Register your custom resource by creating a custom Filament plugin that excludes the default resource.
