---
title: Synchronization
---

# Event Synchronization

The package maintains normalized cart records in the database by listening to events from the `aiarmada/cart` package. This enables efficient querying, analytics, and reporting without impacting cart performance.

## Architecture

```
Cart Events (aiarmada/cart)
    │
    ├── SyncCartOnEvent Listener
    │   ├── CartCreated → Sync cart state
    │   ├── CartCleared → Sync empty cart state
    │   ├── CartDestroyed → Delete normalized cart
    │   ├── ItemAdded/Updated/Removed → Sync cart state
    │   └── ConditionAdded/Removed → Sync cart state
    │
    ├── ApplyGlobalConditions Listener
    │   └── Apply matching global conditions from Condition model
    │
    └── CleanupSnapshotOnCartMerged Listener
        └── Clean up orphaned records after cart merge
```

## Event Listeners

### SyncCartOnEvent

The primary synchronization listener that updates normalized models when cart state changes.

**Handled Events:**
- `CartCreated` — New cart created
- `CartCleared` — Cart cleared (sync empty state)
- `CartDestroyed` — Cart deleted (remove normalized cart)
- `ItemAdded` — Item added to cart
- `ItemUpdated` — Item quantity or attributes changed
- `ItemRemoved` — Item removed from cart
- `CartConditionAdded` — Cart-level condition applied
- `CartConditionRemoved` — Cart-level condition removed
- `ItemConditionAdded` — Item-level condition applied
- `ItemConditionRemoved` — Item-level condition removed

**Behavior:**
- For `CartDestroyed`: Deletes the normalized cart and all related records
- For all other events: Synchronizes current cart state to database

```php
final class SyncCartOnEvent
{
    public function __construct(private CartSyncManager $syncManager) {}

    public function handle($event): void
    {
        if ($event instanceof CartDestroyed) {
            $this->syncManager->deleteByIdentity($event->instance, $event->identifier);
            return;
        }

        $this->syncManager->sync($event->cart);
    }
}
```

### ApplyGlobalConditions

Automatically applies global conditions when carts are created or items change.

**Handled Events:**
- `CartCreated` — Apply all matching global conditions
- `ItemAdded` — Re-evaluate global conditions
- `ItemUpdated` — Re-evaluate global conditions
- `ItemRemoved` — Re-evaluate global conditions

**Behavior:**
1. Fetches active global conditions (`is_global = true`, `is_active = true`)
2. For dynamic conditions, evaluates rules against current cart state
3. Applies matching conditions that aren't already present
4. Removes conditions that no longer match

**Note:** The listener deliberately does NOT listen to `CartUpdated` to avoid infinite loops when applying conditions triggers more condition events.

### CleanupSnapshotOnCartMerged

Handles cleanup when guest carts merge with authenticated user carts.

**Handled Events:**
- `CartMerged` — Clean up orphaned snapshot records

## Services

### NormalizedCartSynchronizer

Core service that transforms cart state into normalized database records.

```php
use AIArmada\FilamentCart\Services\NormalizedCartSynchronizer;

$synchronizer = app(NormalizedCartSynchronizer::class);

// Sync a cart to database
$synchronizer->syncFromCart($cart);

// Delete normalized cart
$synchronizer->deleteNormalizedCart($identifier, $instance);
```

**Synchronization Process:**

1. **Cart Snapshot** — Creates/updates `Cart` model with:
   - Identifier and instance
   - Items count and total quantity
   - Subtotal, total, and savings
   - Currency
   - Full items and conditions as JSON
   - Metadata

2. **Cart Items** — Syncs `CartItem` models:
   - Creates/updates items matching cart state
   - Deletes items no longer in cart
   - Stores price, quantity, attributes, conditions

3. **Cart Conditions** — Syncs `CartCondition` models:
   - Creates/updates cart-level conditions
   - Creates/updates item-level conditions
   - Deletes conditions no longer applied
   - Links item conditions to their CartItem record

### CartSyncManager

High-level manager that coordinates synchronization.

```php
use AIArmada\FilamentCart\Services\CartSyncManager;

$manager = app(CartSyncManager::class);

// Sync a cart
$manager->sync($cart);

// Delete by identity
$manager->deleteByIdentity($instance, $identifier);
```

### CartInstanceManager

Resolves live cart instances from stored identifiers.

```php
use AIArmada\FilamentCart\Services\CartInstanceManager;

$manager = app(CartInstanceManager::class);

// Resolve cart instance
$cart = $manager->resolve($instance, $identifier);
```

This is useful when you need to perform operations on the actual cart from a snapshot record:

```php
$cartModel = Cart::find($id);
$liveCart = $cartModel->getCartInstance();

if ($liveCart) {
    $liveCart->add($item);
}
```

## Queue Configuration

For high-traffic applications, enable queued synchronization to prevent blocking:

```php
// config/filament-cart.php
'synchronization' => [
    'queue_sync' => true,
    'queue_connection' => 'redis',
    'queue_name' => 'cart-sync',
],
```

When enabled:
- Sync operations dispatch to the queue instead of running synchronously
- Cart events return immediately
- A dedicated queue worker processes syncs

Run the worker:

```bash
php artisan queue:work redis --queue=cart-sync
```

## Manual Synchronization

Force-sync carts when needed (e.g., after data repairs):

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\FilamentCart\Services\CartSyncManager;

// Get the cart
$cart = Cart::instance('default');

// Force sync
app(CartSyncManager::class)->sync($cart);
```

### Bulk Synchronization

For bulk operations, you may want to temporarily disable listeners:

```php
use AIArmada\Cart\Events\ItemAdded;

// Disable the listener temporarily
Event::forget(ItemAdded::class);

// Perform bulk operations...
foreach ($items as $item) {
    Cart::add($item);
}

// Manually sync once at the end
app(CartSyncManager::class)->sync(Cart::instance());

// Re-register listeners
// (or just let the next request boot fresh)
```

## Data Model

### Cart (Snapshot)

The `Cart` model stores a complete snapshot of cart state:

```php
$cart = Cart::find($id);

$cart->identifier;      // Session/user identifier
$cart->instance;        // Cart instance name
$cart->items_count;     // Distinct item count
$cart->quantity;        // Total quantity
$cart->subtotal;        // Pre-condition subtotal (cents)
$cart->total;           // Final total (cents)
$cart->savings;         // Discount amount (cents)
$cart->currency;        // Currency code
$cart->items;           // JSON array of items
$cart->conditions;      // JSON array of conditions
$cart->metadata;        // Additional metadata

// Relationships
$cart->cartItems;       // HasMany CartItem
$cart->cartConditions;  // HasMany CartCondition

// Access live cart
$liveCart = $cart->getCartInstance();
```

### CartItem (Snapshot Item)

Individual line items:

```php
$item = CartItem::find($id);

$item->cart_id;         // Parent cart ID
$item->item_id;         // Original cart item ID
$item->name;            // Item name
$item->price;           // Unit price (cents)
$item->quantity;        // Quantity
$item->subtotal;        // Computed: price × quantity
$item->attributes;      // JSON attributes
$item->conditions;      // JSON item-level conditions
$item->associated_model; // Linked model class

// Relationship
$item->cart;            // BelongsTo Cart
```

### CartCondition (Snapshot Condition)

Applied conditions:

```php
$condition = CartCondition::find($id);

$condition->cart_id;        // Parent cart ID
$condition->cart_item_id;   // CartItem ID (if item-level)
$condition->item_id;        // Original item ID (if item-level)
$condition->name;           // Condition name
$condition->type;           // discount, tax, fee, shipping
$condition->target;         // Target expression
$condition->value;          // Value expression
$condition->order;          // Calculation order
$condition->is_percentage;  // Boolean
$condition->is_discount;    // Boolean
$condition->is_charge;      // Boolean
$condition->is_global;      // Boolean
$condition->is_dynamic;     // Boolean

// Relationships
$condition->cart;           // BelongsTo Cart
$condition->cartItem;       // BelongsTo CartItem (nullable)

// Helper methods
$condition->isCartLevel();  // bool
$condition->isItemLevel();  // bool
```

## Consistency Guarantees

### Transactional Sync

All synchronization operations run in database transactions:

```php
DB::transaction(function () use ($cart): void {
    // Update cart model
    $cartModel->save();
    
    // Sync items
    $this->syncItems($cartModel, $items);
    
    // Sync conditions
    $this->syncConditions($cartModel, $conditions);
});
```

### Idempotent Operations

Syncs are idempotent — running the same sync multiple times produces the same result:

- Items are matched by `cart_id` + `item_id`
- Conditions are matched by `cart_id` + `cart_item_id` + `name` + `item_id`
- Orphaned records are cleaned up each sync

## Debugging Synchronization

### Check Sync Status

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\FilamentCart\Models\Cart as CartModel;

$cart = Cart::instance('default');
$identifier = $cart->getIdentifier();

// Check if normalized record exists
$snapshot = CartModel::query()
    ->where('identifier', $identifier)
    ->where('instance', 'default')
    ->first();

if ($snapshot) {
    echo "Synced: {$snapshot->items_count} items, total: {$snapshot->total}";
} else {
    echo "Not synced yet";
}
```

### Force Re-sync

```php
// Force a fresh sync
app(CartSyncManager::class)->sync(Cart::instance());
```

### Compare States

```php
$cart = Cart::instance();
$snapshot = CartModel::where('identifier', $cart->getIdentifier())->first();

// Compare
$liveTotal = (int) $cart->total()->getAmount();
$snapshotTotal = $snapshot->total;

if ($liveTotal !== $snapshotTotal) {
    // Sync is stale
    app(CartSyncManager::class)->sync($cart);
}
```
