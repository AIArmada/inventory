---
title: Basic Usage
---

# Basic Usage

This guide covers the fundamental operations for working with the Cart package.

## Working with Items

### Adding Items

```php
use AIArmada\Cart\Facades\Cart;

// Simple add - price in cents
$item = Cart::add(
    id: 'SKU-001',
    name: 'Product Name',
    price: 2999, // $29.99
    quantity: 1
);

// With attributes
$item = Cart::add(
    id: 'SKU-002',
    name: 'T-Shirt',
    price: 1999,
    quantity: 2,
    attributes: [
        'size' => 'L',
        'color' => 'blue',
    ]
);

// From array
$item = Cart::add([
    'id' => 'SKU-003',
    'name' => 'Another Product',
    'price' => 4999,
    'quantity' => 1,
    'attributes' => ['variant' => 'premium'],
]);

// Multiple items at once
$items = Cart::add([
    ['id' => 'SKU-004', 'name' => 'Item 1', 'price' => 999, 'quantity' => 1],
    ['id' => 'SKU-005', 'name' => 'Item 2', 'price' => 1499, 'quantity' => 2],
]);
```

### Updating Items

```php
// Update quantity (relative)
Cart::update('SKU-001', ['quantity' => 2]); // Adds 2 to current

// Update quantity (absolute)
Cart::update('SKU-001', ['quantity' => ['value' => 5]]); // Sets to 5

// Update price
Cart::update('SKU-001', ['price' => 3499]); // $34.99

// Update attributes
Cart::update('SKU-001', ['attributes' => ['size' => 'XL']]);

// Multiple updates
Cart::update('SKU-001', [
    'quantity' => 3,
    'price' => 2499,
    'attributes' => ['size' => 'M'],
]);
```

### Removing Items

```php
// Remove specific item
Cart::remove('SKU-001');

// Clear all items (keeps conditions)
Cart::clear();

// Destroy cart completely
Cart::destroy();
```

### Retrieving Items

```php
// Get single item
$item = Cart::get('SKU-001');

// Check if item exists
if (Cart::has('SKU-001')) {
    // ...
}

// Get all items
$items = Cart::getItems();

// Search items
$blueItems = Cart::search(function ($item) {
    return $item->attributes->get('color') === 'blue';
});

// Check if empty
if (Cart::isEmpty()) {
    // ...
}
```

## Working with Totals

### Getting Totals

```php
// Get subtotal (with item conditions applied)
$subtotal = Cart::subtotal(); // Returns Money object
echo $subtotal->format(); // "$99.99"

// Get total (all conditions applied)
$total = Cart::total();
echo $total->format(); // "$89.99"

// Get raw values in cents
$subtotalCents = Cart::getRawSubtotal(); // 9999
$totalCents = Cart::getRawTotal(); // 8999

// Get subtotal without ANY conditions
$rawSubtotal = Cart::subtotalWithoutConditions();

// Get savings (discount amount)
$savings = Cart::savings();
echo $savings->format(); // "$10.00"
```

### Quantities

```php
// Total quantity of all items
$totalQuantity = Cart::getTotalQuantity(); // e.g., 15

// Count unique items
$itemCount = Cart::countItems(); // e.g., 5

// Shorthand count (total quantity)
$count = Cart::count();
```

## Working with Conditions

### Simple Conditions

```php
// Add a discount
Cart::addDiscount('10OFF', '-10%');
Cart::addDiscount('Summer Sale', '-500'); // $5.00 off

// Add a fee
Cart::addFee('Service Fee', '+250'); // $2.50

// Add tax
Cart::addTax('VAT', '+7%');

// Add shipping
Cart::addShipping(
    name: 'Standard Shipping',
    value: 599, // $5.99
    method: 'standard'
);
```

### Managing Conditions

```php
// Get all conditions
$conditions = Cart::getConditions();

// Get specific condition
$discount = Cart::getCondition('10OFF');

// Get conditions by type
$taxes = Cart::getConditionsByType('tax');

// Remove condition
Cart::removeCondition('10OFF');

// Remove by type
Cart::removeConditionsByType('discount');

// Clear all conditions
Cart::clearConditions();
```

> Provider-managed conditions (from vouchers, affiliates, shipping, etc.) are synced automatically
> when conditions are read. Avoid manually mutating those conditions; use the provider APIs instead.

## Cart Snapshot Contract

Use `Cart::content()` (or `Cart::getContent()`) to capture a normalized snapshot of the cart state. Checkout sessions store this snapshot as `cart_snapshot`.

```json
{
    "id": "uuid",
    "identifier": "guest-session-id-or-user-id",
    "instance": "default",
    "version": 1,
    "metadata": {},
    "items": [
        {
            "id": "SKU-001",
            "name": "Product Name",
            "price": 4999,
            "quantity": 2,
            "attributes": {
                "weight": 250,
                "dimensions": {"length": 20, "width": 10, "height": 5}
            },
            "conditions": [],
            "associated_model": {
                "class": "App\\Models\\Product",
                "id": "uuid",
                "data": {"sku": "SKU-001"}
            }
        }
    ],
    "conditions": [],
    "subtotal": 9998,
    "total": 9998,
    "quantity": 2,
    "count": 1,
    "item_count": 2,
    "totals": {
        "subtotal": 9998,
        "total": 9998,
        "subtotal_without_conditions": 9998
    },
    "is_empty": false,
    "captured_at": "2026-01-28T10:00:00+00:00",
    "created_at": "2026-01-28T10:00:00+00:00",
    "updated_at": "2026-01-28T10:00:00+00:00"
}
```

Notes:
- `price` and totals are stored in the smallest currency unit (cents).
- `attributes.weight` is in grams when provided.
- `item_count` reflects total quantity; `count` reflects unique line items.
- `associated_model` is populated when cart items are linked to Eloquent models.

## Working with Metadata

```php
// Set metadata
Cart::setMetadata('customer_id', 12345);
Cart::setMetadata('coupon_code', 'SAVE10');

// Set multiple at once
Cart::setMetadataBatch([
    'shipping_address_id' => 789,
    'billing_address_id' => 789,
]);

// Get metadata
$customerId = Cart::getMetadata('customer_id');
$coupon = Cart::getMetadata('coupon_code', 'default');

// Get all metadata
$allMeta = Cart::getAllMetadata();

// Check existence
if (Cart::hasMetadata('customer_id')) {
    // ...
}

// Remove metadata
Cart::removeMetadata('coupon_code');

// Clear all metadata
Cart::clearMetadata();
```

## Cart Instances

Use multiple carts for different purposes:

```php
use AIArmada\Cart\Facades\Cart;

// Default cart
Cart::add('SKU-001', 'Product', 999, 1);

// Wishlist
Cart::instance('wishlist')->add('SKU-002', 'Wishlist Item', 1999, 1);

// Compare list
Cart::instance('compare')->add('SKU-003', 'Compare Item', 2999, 1);

// Get current instance name
$name = Cart::instance(); // 'default'

// Switch back to default
Cart::instance('default');
```

## Cart Content

### Get Full Cart Content

```php
$content = Cart::content();

// Returns:
[
    'id' => 'uuid-here',
    'identifier' => '12345',
    'instance' => 'default',
    'version' => 3,
    'metadata' => [...],
    'items' => [...],
    'conditions' => [...],
    'subtotal' => 9999,
    'total' => 8999,
    'quantity' => 5,
    'count' => 3,
    'is_empty' => false,
    'created_at' => '2024-01-15T10:30:00+00:00',
    'updated_at' => '2024-01-15T10:35:00+00:00',
]
```

### Convert to Array

```php
$array = Cart::toArray(); // Same as content()
```

## Cart Item Properties

```php
$item = Cart::get('SKU-001');

// Basic properties
$item->id;       // 'SKU-001'
$item->name;     // 'Product Name'
$item->price;    // 2999 (cents)
$item->quantity; // 1

// Attributes
$item->attributes->get('size'); // 'L'
$item->attributes->has('color'); // true
$item->attributes->all(); // ['size' => 'L', 'color' => 'blue']

// Calculated values
$item->getRawSubtotal(); // price * quantity in cents
$item->getSubtotal(); // Money object

// Associated model (if set)
$item->getAssociatedModel(); // Eloquent model or null
```
