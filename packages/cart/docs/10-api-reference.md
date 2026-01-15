---
title: API Reference
---

# API Reference

Complete API documentation for the Cart package.

## Cart Facade

The `Cart` facade provides static access to all cart functionality.

### Basic Operations

```php
use AIArmada\Cart\Facades\Cart;

// Instance management
Cart::instance('wishlist');          // Switch to named instance
Cart::instance();                    // Get current instance name

// Item operations
Cart::add(
    id: 'SKU-001',
    name: 'Product Name',
    price: 1999,                     // Price in cents
    quantity: 1,
    attributes: ['size' => 'M'],
    options: ['model_type' => Product::class, 'model_id' => '...']
);
Cart::update('SKU-001', ['quantity' => 2]);
Cart::remove('SKU-001');
Cart::get('SKU-001');                // Get single item
Cart::has('SKU-001');                // Check if item exists
Cart::content();                     // Get all items
Cart::countItems();                  // Count unique items
Cart::getTotalQuantity();            // Sum of all quantities
Cart::clear();                       // Remove all items
Cart::destroy();                     // Destroy cart completely

// Totals
Cart::subtotal();                    // Subtotal as Money object
Cart::getRawSubtotal();              // Subtotal in cents
Cart::total();                       // Total as Money object
Cart::getRawTotal();                 // Total in cents
```

### Condition Operations

```php
// Cart-level conditions
Cart::addCondition($condition);
Cart::addConditions([$condition1, $condition2]);
Cart::getCondition('condition-name');
Cart::getConditions();
Cart::removeCondition('condition-name');
Cart::clearConditions();

// Type-specific helpers
Cart::shipping($name, $value, $attributes);
Cart::tax($name, $value, $attributes);
Cart::discount($name, $value, $attributes);

// Item-level conditions
Cart::addItemCondition('SKU-001', $condition);
Cart::getItemConditions('SKU-001');
Cart::removeItemCondition('SKU-001', 'condition-name');
Cart::clearItemConditions('SKU-001');
```

### Dynamic Conditions

```php
// Registration
Cart::registerDynamicCondition($condition, callable $rule, $metadata);
Cart::registerDynamicCondition($condition, 'subtotal-at-least', ['amount' => 5000]);

// Management
Cart::getDynamicConditions();
Cart::unregisterDynamicCondition('condition-name');
Cart::clearDynamicConditions();

// Evaluation
Cart::evaluateDynamicConditions();
Cart::isDynamicConditionsDirty();
Cart::markDynamicConditionsDirty();
Cart::markDynamicConditionsClean();

// Factory
Cart::setRulesFactory($factory);
Cart::getRulesFactory();
```

### Metadata Operations

```php
Cart::setMetadata('key', 'value');
Cart::setMetadataBatch(['key1' => 'v1', 'key2' => 'v2']);
Cart::getMetadata('key');
Cart::getMetadata('key', 'default');
Cart::getAllMetadata();
Cart::hasMetadata('key');
Cart::removeMetadata('key');
Cart::clearMetadata();
```

### Multi-tenancy

```php
Cart::forOwner($owner);              // Set owner context
Cart::forOwner(null);                // Clear owner context
```

### Pipeline Control

```php
Cart::invalidatePipelineCache();
Cart::withLazyPipeline();
Cart::withoutLazyPipeline();
Cart::isLazyPipelineEnabled();
Cart::getPipelineCacheStats();
```

### Identifiers

```php
Cart::getId();                       // UUID or null
Cart::getIdentifier();               // Session/user ID
Cart::getVersion();                  // Optimistic lock version
```

---

## CartCondition

The core condition class for price modifications.

### Constructor

```php
use AIArmada\Cart\Conditions\CartCondition;

$condition = new CartCondition(
    name: 'discount-10-percent',
    type: ConditionType::Discount,
    target: ConditionTarget::Cart,
    value: '-10%',
    attributes: [
        'description' => '10% Off',
        'priority' => 100,
    ]
);
```

### Value Formats

```php
// Fixed amount (cents)
$condition->setValue(500);           // +$5.00
$condition->setValue(-500);          // -$5.00

// Percentage
$condition->setValue('10%');         // +10%
$condition->setValue('-10%');        // -10%
```

### Methods

```php
// Getters
$condition->getName();
$condition->getType();
$condition->getValue();
$condition->getTargetDefinition();
$condition->getAttributes();
$condition->getAttribute('key', 'default');

// Calculated values
$condition->getCalculatedValue(10000);      // Apply to base value
$condition->apply(10000);                   // Same as above

// Type checks
$condition->isPercentage();
$condition->isDiscount();
$condition->isTax();
$condition->isShipping();
$condition->isFee();

// Targeting
$condition->target();                       // Get ConditionTarget enum
$condition->appliesToCart();
$condition->appliesToSubtotal();
$condition->appliesToItem();

// Serialization
$condition->toArray();
$condition->toStorageArray();
CartCondition::fromStorage($array);
```

---

## CartItem

Read-only item representation.

### Properties

```php
$item->id;                           // Item ID (SKU)
$item->name;                         // Item name
$item->price;                        // Price in cents (int)
$item->quantity;                     // Quantity (int)
$item->attributes;                   // Custom attributes (array)
$item->associatedModel;              // Related Eloquent model or null
```

### Methods

```php
// Totals
$item->subtotal();                   // Money object
$item->getRawSubtotal();             // Cents
$item->total();                      // With conditions, Money
$item->getRawTotal();                // With conditions, cents

// Conditions
$item->getConditions();
$item->hasConditions();

// Model association
$item->getAssociatedModel();
```

---

## Collections

### CartCollection

Collection of CartItem objects.

```php
$collection = Cart::content();

// Methods
$collection->totalQuantity();        // Sum of quantities
$collection->subtotal();             // Sum of subtotals
$collection->findById('SKU-001');    // Find specific item
$collection->toStorageArray();       // Serialize for storage
```

### CartConditionCollection

Collection of CartCondition objects.

```php
$conditions = Cart::getConditions();

// Filter by type
$conditions->ofType(ConditionType::Discount);
$conditions->discounts();
$conditions->taxes();
$conditions->shipping();
$conditions->fees();

// Get specific
$conditions->getByName('condition-name');

// Sort
$conditions->sortByPriority();

// Totals
$conditions->calculateTotal(10000);  // Apply all to base
```

---

## Storage Interface

### StorageInterface Methods

```php
interface StorageInterface
{
    // Configuration
    public function forOwner(Model|string|null $owner): static;
    public function setTableName(string $table): static;

    // Items CRUD
    public function getItems(string $identifier, string $instance): array;
    public function putItems(string $identifier, string $instance, array $items): bool;

    // Conditions CRUD
    public function getConditions(string $identifier, string $instance): array;
    public function putConditions(string $identifier, string $instance, array $conditions): bool;

    // Dynamic conditions CRUD
    public function getDynamicConditions(string $identifier, string $instance): array;
    public function putDynamicConditions(string $identifier, string $instance, array $dynamicConditions): bool;

    // Metadata CRUD
    public function getAllMetadata(string $identifier, string $instance): array;
    public function setMetadata(string $identifier, string $instance, string $key, mixed $value): bool;
    public function getMetadata(string $identifier, string $instance, string $key, mixed $default = null): mixed;
    public function removeMetadata(string $identifier, string $instance, string $key): bool;
    public function clearMetadata(string $identifier, string $instance): bool;

    // Existence
    public function has(string $identifier, string $instance): bool;
    public function getCartId(string $identifier, string $instance): ?string;

    // Lifecycle
    public function clear(string $identifier, string $instance): bool;
    public function destroy(string $identifier, string $instance): bool;
    public function getVersion(string $identifier, string $instance): int;
}
```

---

## Events

All events implement `CartEventInterface` with these methods:

```php
$event->getEventType();              // e.g., 'cart.item.added'
$event->getCartIdentifier();         // Cart identifier
$event->getCartInstance();           // Instance name
$event->getCartId();                 // UUID or null
$event->getEventId();                // Unique event UUID
$event->getEventTimestamp();         // CarbonImmutable
$event->toArray();                   // Serializable data
```

### Event Classes

| Event | Data |
|-------|------|
| `ItemAdded` | `$item`, `$cart` |
| `ItemUpdated` | `$item`, `$cart` |
| `ItemRemoved` | `$item`, `$cart` |
| `CartConditionAdded` | `$condition`, `$cart` |
| `CartConditionRemoved` | `$condition`, `$cart`, `$reason` |
| `ItemConditionAdded` | `$condition`, `$cart`, `$itemId` |
| `ItemConditionRemoved` | `$condition`, `$cart`, `$itemId`, `$reason` |
| `MetadataAdded` | `$key`, `$value`, `$cart` |
| `MetadataBatchAdded` | `$metadata`, `$cart` |
| `MetadataRemoved` | `$key`, `$cart` |
| `MetadataCleared` | `$cart` |
| `CartCleared` | `$cart` |
| `CartCreated` | `$cart` |
| `CartDestroyed` | `$identifier`, `$instance`, `$cartId` |
| `CartMerged` | `$targetCart`, `$sourceCart`, `$totalItemsMerged`, `$mergeStrategy`, `$hadConflicts` |

---

## Exceptions

### CartException

Base exception class.

### CartConflictException

Thrown on optimistic lock conflicts.

```php
$e->getAttemptedVersion();           // Version client had
$e->getCurrentVersion();             // Actual version in storage
$e->getVersionDifference();          // How many versions behind
$e->isMinorConflict();               // True if 1 version behind
$e->getResolutionSuggestions();      // Array of suggestions
$e->getConflictedCart();             // Cart if available
$e->getConflictedData();             // Data if available
$e->toArray();                       // API-friendly format
```

### InvalidCartItemException

Thrown when item validation fails.

### InvalidCartConditionException

Thrown when condition validation fails.

### ProductNotPurchasableException

Thrown when product can't be added.

```php
$e->productId;
$e->productName;
$e->reason;
$e->requestedQuantity;
$e->availableStock;

// Static factories
ProductNotPurchasableException::outOfStock($id, $name, $requested, $available);
ProductNotPurchasableException::inactive($id, $name);
ProductNotPurchasableException::minimumNotMet($id, $name, $requested, $min);
ProductNotPurchasableException::maximumExceeded($id, $name, $requested, $max);
ProductNotPurchasableException::invalidIncrement($id, $name, $requested, $increment);
```

### UnknownModelException

Thrown when associated model class is unknown.

---

## Enums

### ConditionType

```php
enum ConditionType: string
{
    case Tax = 'tax';
    case Shipping = 'shipping';
    case Discount = 'discount';
    case Fee = 'fee';
    case Other = 'other';
}
```

### ConditionTarget

```php
enum ConditionTarget: string
{
    case Cart = 'cart';
    case Item = 'item';
    case Subtotal = 'subtotal';
}
```

### ApplicationStrategy

```php
enum ApplicationStrategy: string
{
    case Aggregate = 'aggregate';
    case PerItem = 'per_item';
    case PerUnit = 'per_unit';
    case PerGroup = 'per_group';
}
```

### PipelinePhase

```php
enum PipelinePhase: string
{
    case PreItem = 'pre_item';
    case ItemDiscount = 'item_discount';
    case ItemPost = 'item_post';
    case CartSubtotal = 'cart_subtotal';
    case Shipping = 'shipping';
    case Taxable = 'taxable';
    case Tax = 'tax';
    case Payment = 'payment';
    case GrandTotal = 'grand_total';
    case Custom = 'custom';
}
```

---

## Artisan Commands

### cart:clear-abandoned

Clear abandoned shopping carts.

```bash
# Clear carts older than 7 days (default)
php artisan cart:clear-abandoned

# Custom days
php artisan cart:clear-abandoned --days=30

# Only clear expired carts (using expires_at)
php artisan cart:clear-abandoned --expired

# Dry run (preview what would be deleted)
php artisan cart:clear-abandoned --dry-run

# Custom batch size
php artisan cart:clear-abandoned --batch-size=500
```

---

## Services

### CartMigrationService

Handles guest-to-user cart migration.

```php
use AIArmada\Cart\Services\CartMigrationService;

$service = app(CartMigrationService::class);

// Migrate guest cart to user
$result = $service->migrateGuestCartForUser($user, 'default', $oldSessionId);

// Access result
$result->success;          // bool
$result->itemsMerged;      // int
$result->conflicts;        // array
$result->message;          // string
```

### BuiltInRulesFactory

Creates rule callables from factory keys.

```php
use AIArmada\Cart\Services\BuiltInRulesFactory;

$factory = app(BuiltInRulesFactory::class);

// Get rule from key
$rule = $factory->make('subtotal-at-least', ['amount' => 5000]);

// Check if key is supported
$factory->supports('subtotal-at-least'); // true
```

### RulePresets

Static factory methods for common rules.

```php
use AIArmada\Cart\Services\RulePresets;

// Value rules
$rule = RulePresets::subtotalAtLeast(5000);
$rule = RulePresets::subtotalBetween(1000, 10000);
$rule = RulePresets::totalAtMost(50000);

// Quantity rules
$rule = RulePresets::quantityAtLeast(3);
$rule = RulePresets::itemCountAtLeast(2);

// Product rules
$rule = RulePresets::hasAnyProduct(['SKU-1', 'SKU-2']);
$rule = RulePresets::hasAllProducts(['SKU-1', 'SKU-2']);
$rule = RulePresets::hasProductCategory('electronics');

// Time rules
$rule = RulePresets::onWeekdays();
$rule = RulePresets::onWeekends();
$rule = RulePresets::duringHours(9, 17);
$rule = RulePresets::duringDateRange($start, $end);

// Customer rules
$rule = RulePresets::forAuthenticatedUsers();
$rule = RulePresets::forGuests();
$rule = RulePresets::forCustomerTier('premium');
$rule = RulePresets::isFirstTimeCustomer();

// Metadata rules
$rule = RulePresets::hasMetadata('coupon_code');
$rule = RulePresets::metadataEquals('channel', 'mobile');

// Combinators
$rule = RulePresets::all($rule1, $rule2);    // AND
$rule = RulePresets::any($rule1, $rule2);    // OR
$rule = RulePresets::not($rule);             // NOT
```
